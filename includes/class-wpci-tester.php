<?php
if (!defined('ABSPATH')) exit;

class WPCI_Tester {

    /**
     * Run a single HTTP probe and normalize the response.
     *
     * @param string $label
     * @param string $url
     * @param array  $args
     * @return array
     */
    private function probe($label, $url, $args = []) {
        $t0 = microtime(true);

        // Default args, allow overrides via $args
        $res = wp_remote_get($url, array_merge([
            'timeout'     => 10,
            'sslverify'   => false, // keep lenient for common host configs
            'headers'     => [],
            'redirection' => 2,
            'decompress'  => true,
        ], $args));

        $elapsed = microtime(true) - $t0;

        if (is_wp_error($res)) {
            return [
                'label'   => $label,
                'status'  => 'ERROR: ' . $res->get_error_message(),
                'elapsed' => $elapsed,
                'headers' => [],
            ];
        }

        $code        = wp_remote_retrieve_response_code($res);
        $raw_headers = wp_remote_retrieve_headers($res);
        $headers     = $this->normalize_headers($raw_headers);

        return [
            'label'   => $label,
            'status'  => (string) $code,
            'elapsed' => $elapsed,
            'headers' => $headers,
        ];
    }

    /**
     * Normalize headers from wp_remote_get into a simple
     * lowercase-key => string-value array.
     *
     * @param mixed $raw_headers
     * @return array
     */
    private function normalize_headers($raw_headers) {
        $headers = [];

        // Case 1: already an array
        if (is_array($raw_headers)) {
            foreach ($raw_headers as $k => $v) {
                $lk = strtolower((string) $k);
                $headers[$lk] = is_array($v) ? implode(', ', $v) : (string) $v;
            }
            return $headers;
        }

        // Case 2: WP_Http_Headers object
        if (is_object($raw_headers) && method_exists($raw_headers, 'getAll')) {
            $all = $raw_headers->getAll();
            if (is_array($all)) {
                foreach ($all as $k => $v) {
                    $lk = strtolower((string) $k);
                    $headers[$lk] = is_array($v) ? implode(', ', $v) : (string) $v;
                }
            }
        }

        return $headers;
    }

    /**
     * Run the standard probe suite used by the admin screen.
     *
     * @return array|WP_Error
     */
    public function run_suite() {
        $home = home_url('/');

        $requests = [];

        // 1) Home #1 (baseline)
        $requests[] = $this->probe('Home #1 (baseline)', add_query_arg('wpci_probe', '1', $home));

        // 2) Home #2 (check for HIT or AGE)
        $requests[] = $this->probe('Home #2 (check HIT/AGE)', add_query_arg('wpci_probe', '1', $home));

        // 3) REST ping (must not be cached)
        $requests[] = $this->probe('REST /wpci/v1/ping', rest_url('wpci/v1/ping'));

        // 4) Home with request header no-cache
        $requests[] = $this->probe(
            'Home (request Cache-Control: no-cache)',
            add_query_arg('wpci_probe', '2', $home),
            ['headers' => ['Cache-Control' => 'no-cache']]
        );

        return ['requests' => $requests];
    }
}
