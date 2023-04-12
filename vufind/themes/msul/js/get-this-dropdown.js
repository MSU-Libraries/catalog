
$(document).ready(function() {
    console.log("get-this-dropdown loaded.");
    $(".modal").on("click", ".get-this-dropdown > ul > li > a", function(e) {
        console.log("get-this-dropdown click!");
        $(this).siblings("div").toggleClass('active');
        e.stopPropagation();
    });
});
