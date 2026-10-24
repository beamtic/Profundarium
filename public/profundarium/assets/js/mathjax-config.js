// Only typeset math nodes produced by the Markdown parser. Code and ordinary
// dollar amounts are left alone. SVG output also works in browser PDF exports.
window.MathJax = {
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
