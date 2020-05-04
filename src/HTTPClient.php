<?php

namespace StaticWeb;

// Modified from https://github.com/WP2Static/wp2static/blob/fe4877fe235c3cc73fbda923adfbd50c8d82c9e8/src/Crawler.php

class HTTPClient {

    /**
     * @var resource | bool
     */
    private $ch;
    /**
     * @var Request
     */
    private $request;
    /**
     * @var mixed[]
     */
    private $curl_options;

    /**
     * Crawler constructor
     */
    public function __construct() {
        $this->ch = curl_init();
        $this->request = new \WP2Static\Request();

        $port_override = apply_filters(
            'wp2static_curl_port',
            null
        );

        // Setting CURLOPT_FOLLOWLOCATION wasn't enough in testing,
        // so we also set CURLOPT_MAXREDIRS.
        curl_setopt( $this->ch, CURLOPT_FOLLOWLOCATION, false );
        curl_setopt( $this->ch, CURLOPT_MAXREDIRS, 0 );

        // TODO: apply this filter when option is saved
        if ( $port_override ) {
            curl_setopt( $this->ch, CURLOPT_PORT, $port_override );
        }

        curl_setopt(
            $this->ch,
            CURLOPT_USERAGENT,
            apply_filters( 'wp2static_curl_user_agent', 'WP2Static.com' )
        );

        $auth_user = \WP2Static\CoreOptions::getValue( 'basicAuthUser' );
        $auth_password = \WP2Static\CoreOptions::getValue( 'basicAuthPassword' );

        if ( $auth_user || $auth_password ) {
            curl_setopt(
                $this->ch,
                CURLOPT_USERPWD,
                $auth_user . ':' . $auth_password
            );
        }
    }

    public function getURL( \WP2Static\URL $url ) : array {
        return $this->request->getURL( $url->get(), $this->ch );
    }
}
