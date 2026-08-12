module.exports = {
    purge: [
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],
    plugins: [
        require('@tailwindcss/custom-forms')
    ]
}
