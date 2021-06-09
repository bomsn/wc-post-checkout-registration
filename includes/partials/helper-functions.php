<?php
if (!function_exists('wc_pcr_generate_random_token')) :

    function wc_pcr_generate_random_token($length)
    {
        $length = (int) $length;

        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $token = '';

        for ($i = 0; $i < $length; $i++) {
            $token .= substr($chars, wp_rand(0, strlen($chars) - 1), 1);
        }

        return $token;
    }

endif;
