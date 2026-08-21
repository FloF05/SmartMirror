(() => {

    const config = (window.mirrorConfig && window.mirrorConfig.slideshow) || {};
    const interval = parseInt(config.interval, 10) || 5000;

    const images = document.querySelectorAll(".slideshow-image");

    if (images.length === 0) {
        return;
    }

    let current = 0;

    const showImage = index => {
        images.forEach(image => image.classList.remove("active"));
        images[index].classList.add("active");
    };

    showImage(current);

    if (images.length < 2) {
        return;
    }

    setInterval(() => {
        current = (current + 1) % images.length;
        showImage(current);
    }, interval);

})();
