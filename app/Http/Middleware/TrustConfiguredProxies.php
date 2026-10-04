<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use LogicException;

class TrustConfiguredProxies extends TrustProxies
{
    protected function setTrustedProxyIpAddresses(Request $request): void
    {
        $proxies = config('security.trusted_proxies', []);
        foreach ($proxies as $proxy) {
            [$address, $prefix] = array_pad(explode('/', $proxy, 2), 2, null);
            $max = filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? 32 : 128;
            if (! filter_var($address, FILTER_VALIDATE_IP) || ($prefix !== null && (! ctype_digit($prefix) || (int) $prefix > $max))) {
                throw new LogicException('TRUSTED_PROXIES must contain explicit IP addresses or CIDR ranges.');
            }
        }
        // Do not enable the framework's automatic trust for cloud-hostname suffixes.
        $request->setTrustedProxies($proxies, Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PROTO | Request::HEADER_X_FORWARDED_PORT);
    }
}
