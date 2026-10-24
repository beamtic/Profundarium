// Only typeset math nodes produced by the Markdown parser. Code and ordinary
// dollar amounts are left alone. SVG output also works in browser PDF exports.
window.MathJax = {
    loader: {
        paths: {
            mathjax: '/profundarium/assets/vendor/mathjax',
            fonts: '/profundarium/assets/vendor/mathjax/fonts',
            'mathjax-newcm': '/profundarium/assets/vendor/mathjax/fonts/mathjax-newcm-font',
            'mathjax-mhchem-extension': '/profundarium/assets/vendor/mathjax/fonts/mathjax-mhchem-font-extension',
            'mathjax-bbm-extension': '/profundarium/assets/vendor/mathjax/fonts/mathjax-bbm-font-extension',
            'mathjax-bboldx-extension': '/profundarium/assets/vendor/mathjax/fonts/mathjax-bboldx-font-extension',
            'mathjax-dsfont-extension': '/profundarium/assets/vendor/mathjax/fonts/mathjax-dsfont-font-extension'
        }
    },
    output: {
        fontPath: '/profundarium/assets/vendor/mathjax/fonts/%%FONT%%-font'
    },
    svg: {
        dynamicPrefix: '/profundarium/assets/vendor/mathjax/fonts/mathjax-newcm-font/svg/dynamic'
    },
    options: {
        ignoreHtmlClass: 'mathjax-ignore',
        processHtmlClass: 'mathjax-process'
    },
    tex: {
        inlineMath: [['\\(', '\\)']],
        displayMath: [['\\[', '\\]']],
        processEnvironments: false
    }
};
