const slideshowImages =
document.querySelectorAll(".slideshow-image");


let currentImage = 0;


function showImage(index)
{

    slideshowImages.forEach(
        image =>
        image.classList.remove("active")
    );


    slideshowImages[index]
    .classList.add("active");

}


if(slideshowImages.length > 0)
{

    showImage(currentImage);


    setInterval(() =>
    {

        currentImage++;


        if(
            currentImage
            >= slideshowImages.length
        )
        {
            currentImage = 0;
        }


        showImage(currentImage);

    }, 5000);

}