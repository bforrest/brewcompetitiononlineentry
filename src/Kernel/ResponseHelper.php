<?php
declare(strict_types=1);

namespace Bcoem\Kernel;

use Psr\Http\Message\ResponseInterface;

final class ResponseHelper
{
    public static function json(ResponseInterface $response, mixed $data, int $status = 200): ResponseInterface
    {
        $response->getBody()->write((string) json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        return $response
            ->withStatus($status)
            ->withHeader('Content-Type', 'application/json');
    }

    public static function html(ResponseInterface $response, string $html, int $status = 200): ResponseInterface
    {
        $response->getBody()->write($html);
        return $response
            ->withStatus($status)
            ->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    public static function text(ResponseInterface $response, string $text, int $status = 200): ResponseInterface
    {
        $response->getBody()->write($text);
        return $response
            ->withStatus($status)
            ->withHeader('Content-Type', 'text/plain; charset=utf-8');
    }
}
