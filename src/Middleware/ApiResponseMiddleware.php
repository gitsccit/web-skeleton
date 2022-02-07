<?php

namespace Skeleton\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Response middleware
 */
class ApiResponseMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        if (strpos($request->getUri()->getPath(), 'preview')) {
            return $response;
        }

        if (strpos($request->getUri()->getPath(), 'assurex')) {
            return $this->assurex_flatten_data($response);
        }

        $body = json_decode($response->getBody());
        $message = $body->message ?? $response->getReasonPhrase();
        if (is_array($body)) {
            unset($body->message);
        }

        $data = [
            'message' => $message,
            'data' => $body,
        ];

        return $response->withStringBody(json_encode($data));
    }

    private function assurex_flatten_data($response)
    {

        $json = json_decode($response->getBody());

        // flatten the response body (required by assurex)
        $data = ['found' => !empty($json->found)];
        if ($data['found']) {
            foreach ($json as $value) {
                if (is_array($value)) {
                    foreach ($value as $i => $line) {
                        $this->flatten_array($data, '', $line, $i + 1);
                    }
                } else {
                    $this->flatten_array($data, '', $value);
                }
            }
        }

        if ($data['found'] === false && !empty($json->header)) {
            $data = ['found' => true];
            $data['found'] = true;
            $this->flatten_array($data, '', $json->header);
        }

        $response = $response->withStringBody(json_encode($data));
        return $response;

    }

    private function flatten_array(&$data, $pre, $source, $post = '')
    {

        if (is_scalar($source) || $source == null) {
            return;
        }

        foreach ($source as $key => $value) {
            $k = (substr($key, 0, strlen($pre)) == $pre ? $key : $pre . $key);
            if (is_scalar($value)) {
                $data[$k . $post] = $value;
            } else {
                $this->flatten_array($data, $k, $value, $post);
            }
        }

    }
}
