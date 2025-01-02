<?php
# Copyright (c) 2013-2025, OVH SAS.
# All rights reserved.
#
# Redistribution and use in source and binary forms, with or without
# modification, are permitted provided that the following conditions are met:
#
#   * Redistributions of source code must retain the above copyright
#     notice, this list of conditions and the following disclaimer.
#   * Redistributions in binary form must reproduce the above copyright
#     notice, this list of conditions and the following disclaimer in the
#     documentation and/or other materials provided with the distribution.
#   * Neither the name of OVH SAS nor the
#     names of its contributors may be used to endorse or promote products
#     derived from this software without specific prior written permission.
#
# THIS SOFTWARE IS PROVIDED BY OVH SAS AND CONTRIBUTORS ``AS IS'' AND ANY
# EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
# WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
# DISCLAIMED. IN NO EVENT SHALL OVH SAS AND CONTRIBUTORS BE LIABLE FOR ANY
# DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
# (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
# LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
# ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
# (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
# SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.

namespace Ovh;

use League\OAuth2\Client\OptionProvider\PostAuthOptionProvider;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Provider\GenericProvider;
use UnexpectedValueException;

class OAuth2
{
    private $provider;
    private $token;

    public function __construct($clientId, $clientSecret, $tokenUrl)
    {

        $this->provider = new \League\OAuth2\Client\Provider\GenericProvider([
            'clientId'                => $clientId,
            'clientSecret'            => $clientSecret,
            # Do not configure `scopes` here as this GenericProvider ignores it when using client credentials flow
            'urlAccessToken'          => $tokenUrl,
            'urlAuthorize'            => null, # GenericProvider wants it but OVHcloud doesn't provide it, as it's not needed for client credentials flow
            'urlResourceOwnerDetails' => null, # GenericProvider wants it but OVHcloud doesn't provide it, as it's not needed for client credentials flow
        ]);
    }

    public function getAuthorizationHeader()
    {
        if (is_null($this->token) ||
            $this->token->hasExpired() ||
            $this->token->getExpires() - 10 <= time()) {
            try {
                $this->token = $this->provider->getAccessToken('client_credentials', ['scope' => 'all']);
            } catch (UnexpectedValueException | IdentityProviderException $e) {
                throw new Exceptions\OAuth2FailureException('OAuth2 failure: ' . $e->getMessage(), $e->getCode(), $e);
            }
        }

        return 'Bearer ' . $this->token->getToken();
    }
}
