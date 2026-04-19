<?php
namespace Rehike\Controller\special;

use Rehike\YtApp;
use Rehike\ControllerV2\RequestMetadata;

use Rehike\ConfigManager\Config;

use function Rehike\Async\async;
use Rehike\SimpleFunnel;
use Rehike\SimpleFunnelResponse;
use Rehike\Network\Internal\Response;
use Rehike\Controller\core\HitchhikerController;
use Rehike\ControllerV2\IPostController;

/**
 * Proxies player requests and removes ads.
 * 
 * @author Taniko Yamamoto <kirasicecreamm@gmail.com>
 * @author The Rehike Maintainers
 */
class InnertubePlayerProxyController extends HitchhikerController implements IPostController
{
    public bool $useTemplate = false;

    public function onPost(YtApp $yt, RequestMetadata $request): void
    {
        async(function() use (&$yt, &$request) {
            $response = yield SimpleFunnel::funnelCurrentPage();
            
            if (true == Config::getConfigProp("appearance.enableAdblock"))
            {
                $data = $response->getJson();

                if (isset($data->playerAds))
                    unset($data->playerAds);

                if (isset($data->adPlacements))
                    unset($data->adPlacements);
                
                if (isset($data->adSlots))
                    unset($data->adSlots);

                // Strip SABR streaming protocol for legacy browser compatibility
                if (isset($data->streamingData))
                {
                    $sd = $data->streamingData;

                    if (isset($sd->serverAbrStreamingUrl))
                        unset($sd->serverAbrStreamingUrl);

                    if (isset($sd->adaptiveFormats) && is_array($sd->adaptiveFormats))
                    {
                        $filtered = [];
                        foreach ($sd->adaptiveFormats as $format)
                        {
                            if (isset($format->mimeType))
                            {
                                $mime = strtolower($format->mimeType);
                                if (
                                    strpos($mime, 'vp9') !== false ||
                                    strpos($mime, 'vp09') !== false ||
                                    strpos($mime, 'av01') !== false ||
                                    strpos($mime, 'opus') !== false
                                ) {
                                    continue;
                                }
                            }
                            if (isset($format->url))
                            {
                                $format->url = preg_replace('/([&?])sabr=1/', '$1', $format->url);
                                $format->url = preg_replace('/([&?])rqh=1/', '$1', $format->url);
                                $format->url = preg_replace('/[&?]$/', '', $format->url);
                                $format->url = str_replace('&&', '&', $format->url);
                            }
                            $filtered[] = $format;
                        }
                        $sd->adaptiveFormats = $filtered;
                    }

                    if (isset($sd->formats) && is_array($sd->formats))
                    {
                        foreach ($sd->formats as $format)
                        {
                            if (isset($format->url))
                            {
                                $format->url = preg_replace('/([&?])sabr=1/', '$1', $format->url);
                                $format->url = preg_replace('/([&?])rqh=1/', '$1', $format->url);
                                $format->url = preg_replace('/[&?]$/', '', $format->url);
                                $format->url = str_replace('&&', '&', $format->url);
                            }
                        }
                    }
                }

                $modifiedResponse = json_encode($data);
            }
            else
            {
                $modifiedResponse = $response->getText();
            }

            // PHP doesn't let you cast objects (like: (array) $response->headers)
            // and the Response constructor does not accept ResponseHeaders for
            // the headers so we must convert it manually
            $headers = [];
            foreach ($response->headers as $name => $value)
            {
                $headers[$name] = $value;
            }

            SimpleFunnelResponse::fromResponse(
                new Response(
                    $response->sourceRequest, 
                    $response->status,
                    $modifiedResponse,
                    $headers
                )
            )->output();
        });
    }
}