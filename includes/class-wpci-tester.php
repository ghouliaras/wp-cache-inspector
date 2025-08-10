<?php
if (!defined('ABSPATH')) exit;

class WPCI_Tester {

    private function probe($label, $url, $args = []) {
        $t0 = microtime(true);
        $res = wp_remote_get($url, array_merge([
            'timeout' => 10,
            'sslverify' => false,
            'headers' => [],
            'redirection' => 2,
        ], $args));
        $elapsed = microtime(true) - $t0;

        if (is_wp_error($res)) {
            return [
                'label' => $label,
                'status' => 'ERROR: ' . $res->get_error_message(),
                'elapsed' => $elapsed,
                'headers' => [],
            ];
        }

        $code = wp_remote_retrieve_response_code($res);
        $raw_headers = wp_remote_retrieve_headers($res);
        $headers = [];
        if (is_array($raw_headers)) {
            foreach ($raw_headers as $k => $v) $headers[strtolower($k)] = $v;
        } elseif (is_object($raw_headers) && isset($raw_headers->getAll())) {
            foreach ($raw_headers->getAll() as $k => $v) $headers[strtolower($k)] = is_array($v) && count($v)===1 ? $v[0] : $v;
        }

        return [
            'label' => $label,
            'status' => (string)$code,
            'elapsed' => $elapsed,
            'headers' => $headers,
        ];
    }

    public function run_suite() {
        $home = home_url('/');
        $requests = [];

        // 1) Home #1 (baseline)
        $requests[] = $this->probe('Home #1 (baseline)', add_query_arg('wpci_probe','1',$home));

        // 2) Home #2 (check for HIT or AGE)
        $requests[] = $this->probe('Home #2 (check HIT/AGE)', add_query_arg('wpci_probe','1',$home));

        // 3) REST ping (must not be cached)
        $requests[] = $this->probe('REST /wpci/v1/ping', rest_url('wpci/v1/ping'));

        // 4) Home with request header no-cache
        $requests[] = $this->probe('Home (request Cache-Control: no-cache)', add_query_arg('wpci_probe','2',$home), [
            'headers' => ['Cache-Control' => 'no-cache'],
        ]);

        return ['requests' => $requests];
    }
}
