
$(document).ready(function() {
    $(".modal").on("click", ".get-this-dropdown > ul > li > a", function(e) {
        $(this).siblings("div").toggleClass('active');
        $(this).attr("aria-expanded", function(index, attr) {
            return attr == "true" ? "false" : "true";
        });
        e.stopPropagation();
        return false;
    });
    $(".modal").on("focusout", ".get-this-dropdown", function(e) {
        var dropdown = $(this).get(0);
        // Need delay to let new focus to happen
        setTimeout(function () {
            // Then check if new focus has left the dropdown
            if (! $.contains(dropdown, document.activeElement)) {
                $(".get-this-dropdown div").removeClass('active');
                $(".get-this-dropdown > ul > li > a").attr("aria-expanded", "false");
            }
        }, 1);
    });
    $(".modal").on("keydown", function(e) {
        // Handle arrow key navidation of dropdown selection
        var dropdown = $(".get-this-dropdown div").get(0);
        if ($.contains(dropdown, document.activeElement)) {
            var par = $(document.activeElement).parent();
            if (e.keyCode == 38 && par.prev("li").length > 0) {
                par.prev("li").children("a").focus();
                return false;
            }
            else if (e.keyCode == 40 && par.next("li").length > 0) {
                par.next("li").children("a").focus();
                return false;
            }
        }
    });
});
