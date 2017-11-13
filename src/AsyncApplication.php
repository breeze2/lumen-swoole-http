<?php

namespace BL\SwooleHttp;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Laravel\Lumen\Application as LumenApplication;
use Psr\Http\Message\ResponseInterface as PsrResponseInterface;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class AsyncApplication extends LumenApplication
{

    public function getMiddleware()
    {
        return $this->middleware;
    }

    public function callTerminableMiddleware($response)
    {
        parent::callTerminableMiddleware($response);
    }

    public function prepareResponse($response)
    {
        $response = yield $response;

        if ($response instanceof Responsable) {
            $response = $response->toResponse(Request::capture());
        }

        if ($response instanceof PsrResponseInterface) {
            $response = (new HttpFoundationFactory)->createResponse($response);
        } elseif (!$response instanceof SymfonyResponse) {
            $response = new Response($response);
        } elseif ($response instanceof BinaryFileResponse) {
            $response = $response->prepare(Request::capture());
        }

        return $response;
    }
}
