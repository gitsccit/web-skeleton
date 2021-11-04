<?php
declare(strict_types=1);

namespace Skeleton\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * RequestSanitation middleware
 */
class RequestSanitationMiddleware implements MiddlewareInterface
{
    /**
     * @var array A list of valid credit card fields.
     */
    protected $creditCardFields = ['credit_card'];

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $body = $request->getParsedBody();
        foreach ($body as $key => $value) {
            if (!is_string($value) || !is_numeric($value) || in_array($key, $this->creditCardFields)) {
                continue;
            }
            $body[$key] = $this->sanitize((string)$value);
        }
        $request = $request->withParsedBody($body);

        return $handler->handle($request);
    }

    /**
     * @param string $field
     */
    protected function sanitize($field)
    {
        if (is_array($field)) {
            return array_map([$this, 'sanitize'], $field);
        }

        return $this->cc_regex($field);
    }

    protected function cc_regex($text)
    {
        // http://www.richardsramblings.com/2012/12/the-perfect-credit-card-number-regex/
        // code green network's regex pattern
        $pattern = '/\b(?:\d[ -]*?){13,16}\b/';
        $replaced = false;
        // $pattern = '/\b(3[47]\d{2}([ -]?)(?!(\d)\3{5}|123456|234567|345678)\d{6}\2(?!(\d)\4{4})\d{5}|((4\d|5[1-5]|65)\d{2}|6011)([ -]?)(?!(\d)\8{3}|1234|3456|5678)\d{4}\7(?!(\d)\9{3})\d{4}\7\d{4})\b';
        if (preg_match($pattern, $text, $m, PREG_OFFSET_CAPTURE)) {
            foreach ($m as $match) {
                $n = preg_replace('/\D/', '', $match[0]);
                if (strlen($n) >= 13 && strlen($n) <= 16 && $this->cc_strict_regex($n) && $this->is_valid_luhn($n)) {
                    if (!$replaced) {
                        $replaced = $text;
                    }
                    // wipe out credit card
                    $replaced = $this->string_x_out($replaced, $match[1], $match[1] + strlen($match[0]), true);
                    // look for nearby expires and cvv
                    $replaced = $this->string_x_out(
                        $replaced,
                        max(0, $match[1] - 10),
                        $match[1] + strlen($match[0]) + 30,
                        false
                    );
                }
            }
        }

        return $replaced ?: $text;
    }

    protected function cc_strict_regex($number)
    {
        return preg_match('/\b(?:3[47]\d|(?:4\d|5[1-5]|65)\d{2}|6011)\d{12}\b/', $number, $m);
    }

    protected function is_valid_luhn($number)
    {
        settype($number, 'string');
        $sumTable = [
            [0, 1, 2, 3, 4, 5, 6, 7, 8, 9],
            [0, 2, 4, 6, 8, 1, 3, 5, 7, 9],
        ];
        $sum = 0;
        $flip = 0;
        for ($i = strlen($number) - 1; $i >= 0; $i--) {
            $sum += $sumTable[$flip++ & 0x1][$number[$i]];
        }

        return $sum % 10 === 0;
    }

    protected function string_x_out($text, $start, $stop, $all = false)
    {
        $temp = substr($text, $start, $stop - $start);
        if ($all) {
            $temp = preg_replace("/\d/", "X", $temp);
        } else {
            $temp = preg_replace("/\d{4}/", "XXXX", $temp);
            $temp = preg_replace("/\d{3}/", "XXX", $temp);
            $temp = preg_replace("/\d{2}/", "XX", $temp);
        }
        $temp = substr($text, 0, $start) . $temp . substr($text, $stop);

        return $temp;
    }
}
