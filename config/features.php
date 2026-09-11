<?php

return [
    'kroo_iq_requires_kroo_plus' => filter_var(
        env('KROO_IQ_REQUIRES_KROO_PLUS', true),
        FILTER_VALIDATE_BOOL,
    ),
];
