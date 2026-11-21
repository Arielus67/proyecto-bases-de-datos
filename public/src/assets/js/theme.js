//add Shadow to header on scroll
document.addEventListener("DOMContentLoaded", function () {
    document.addEventListener("scroll", function () {
        var encabezado = document.querySelector(".top-header");
        if (!encabezado) return;
        if (document.body.scrollTop > 60 || document.documentElement.scrollTop > 60) {
            encabezado.classList.add("shadow-sm");
        } else {
            encabezado.classList.remove("shadow-sm");
        }
    })
});
