<?php
namespace BL\SwooleHttp\Dingo;

use Dingo\Api\Http\Request;
use Dingo\Api\Http\Response;
use Dingo\Api\Routing\Router as DingoRouter;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response as IlluminateResponse;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotAcceptableHttpException;

class Router extends DingoRouter
{
    /**
     * Prepare a response by transforming and formatting it correctly.
     *
     * @param mixed                   $response
     * @param \Dingo\Api\Http\Request $request
     * @param string                  $format
     *
     * @return \Dingo\Api\Http\Response
     */
    protected function prepareResponse($response, Request $request, $format)
    {
        $response = yield $response;
        if ($response instanceof IlluminateResponse) {
            $response = Response::makeFromExisting($response);
        } elseif ($response instanceof JsonResponse) {
            $response = Response::makeFromJson($response);
        }

        if ($response instanceof Response) {
            // If we try and get a formatter that does not exist we'll let the exception
            // handler deal with it. At worst we'll get a generic JSON response that
            // a consumer can hopefully deal with. Ideally they won't be using
            // an unsupported format.
            try {
                $response->getFormatter($format)->setResponse($response)->setRequest($request);
            } catch (NotAcceptableHttpException $exception) {
                return $this->exception->handle($exception);
            }

            $response = $response->morph($format);
        }

        if ($response->isSuccessful() && $this->requestIsConditional()) {
            if (!$response->headers->has('ETag')) {
                $response->setEtag(sha1($response->getContent()));
            }

            $response->isNotModified($request);
        }

        return $response;
    }
}
