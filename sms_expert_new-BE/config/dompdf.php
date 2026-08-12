<?php

return [
    'show_warnings' => false,   // Throw an Exception on warnings from dompdf
    'public_path' => null,       // Override the public path if needed
    
    'convert_entities' => true,
    
    'options' => [
        /**
         * The location of the DOMPDF font directory
         */
        'font_dir' => storage_path('fonts/'),

        /**
         * The location of the DOMPDF font cache directory
         */
        'font_cache' => storage_path('fonts/'),

        /**
         * The location of a temporary directory.
         */
        'temp_dir' => sys_get_temp_dir(),

        /**
         * Whether to enable font subsetting or not.
         */
        'font_subsetting' => false,

        /**
         * The PDF rendering backend to use
         */
        'pdf_backend' => 'CPDF',

        /**
         * html target media view which should be rendered into pdf
         */
        'default_media_type' => 'screen',

        /**
         * The default paper size.
         */
        'default_paper_size' => 'a4',

        /**
         * The default font family
         */
        'default_font' => 'serif',

        /**
         * Whether to enable remote file access
         */
        'enable_remote' => true,

        /**
         * A ratio applied to the fonts height to be more like browsers' line height
         */
        'font_height_ratio' => 1.1,

        /**
         * Use the more-than-experimental HTML5 Lib parser
         */
        'enable_html5_parser' => false,
    ],
];
