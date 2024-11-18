<?php

// src/Utils/PesapalHelpers.php

namespace Katorymnd\PesapalPhpSdk\Utils;

class PesapalHelpers
{
    /**
     * Generates a unique merchant reference.
     *
     * @return string A unique merchant reference string like "BB2345-6789XW"
     */
    public static function generateMerchantReference()
    {
        return strtoupper(sprintf(
            '%04s%04s-%04s',
            substr(md5(uniqid(mt_rand(), true)), 0, 4),
            substr(md5(uniqid(mt_rand(), true)), 0, 4),
            substr(md5(uniqid(mt_rand(), true)), 0, 4)
        ));
    }
}