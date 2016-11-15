$(document).ready(function () {
    get_mod_connectslide_filter();

    mod_connectslide_add_tooltip();
    
    $('body').on("click", "#connectslide-update-from-adobe", function(event){
        event.preventDefault();
        var block = $(this);
        var connectslide_id = block.data('connectslideid');
        $('#connectslidecontent' + connectslide_id ).html('');
        $('#connectslidecontent' + connectslide_id ).addClass('rt-loading-image');
        $.ajax({
            url: window.wwwroot + "/mod/connectslide/ajax/connectslide_callback.php",
            dataType: "html",
            data: {
                connectslide_id: connectslide_id,
                update_from_adobe: 1,
            }
        }).done(function (data) {
            $('#connectslidecontent' + connectslide_id ).removeClass('rt-loading-image');
            $('#connectslidecontent' + connectslide_id ).html(data);
            mod_connectslide_add_tooltip();
        });
    });
});

function mod_connectslide_add_tooltip(){
    if (typeof($.uitooltip) != 'undefined') {
        $('.mod_connectslide_tooltip').uitooltip({
            show: null, // show immediately
            items: '.mod_connectslide_tooltip',
            content: function () {
                return $(this).next('.mod_connectslide_popup').html();
            },
            position: {my: "left top", at: "right top", collision: "flipfit"},
            hide: {
                effect: "" // fadeOut
            },
            open: function (event, ui) {
                ui.tooltip.animate({left: ui.tooltip.position().left + 10}, "fast");
            },
            close: function (event, ui) {
                ui.tooltip.hover(
                    function () {
                        $(this).stop(true).fadeTo(400, 1);
                    },
                    function () {
                        $(this).fadeOut("400", function () {
                            $(this).remove();
                        })
                    }
                );
            }
        });
    }
}

function add_mod_connectslide_filter_alert(block, type, msg) {
    block.html(
        '<div class="fitem" id="fgroup_id_urlgrp_alert">' +
        '<div class="felement fstatic alert alert-' + type + ' alert-dismissible">' +
        '<button type="button" class="close" data-dismiss="alert"><span aria-hidden="true">&times;</span></button>' +
        msg +
        '</div>' +
        '</div>'
    );
}

function get_mod_connectslide_filter() {
    $('.connectslide_display_block').each(function (index) {
        var block = $(this);
        var acurl = block.data('acurl');
        var sco = block.data('sco');
        var courseid = block.data('courseid');
        block.removeClass('connectslide_display_block').addClass('connectslide_display_block_done');
        $.ajax({
            url: window.wwwroot + "/mod/connectslide/ajax/connectslide_callback.php",
            dataType: "html",
            data: {
                acurl: acurl,
                sco: sco,
                courseid: courseid,
                options: encodeURIComponent(block.data('options')),
                frommymeetings: block.data('frommymeetings'),
                frommyrecordings: block.data('frommyrecordings')
            }
        }).done(function (data) {
            block.html(data);
        }).fail(function (jqXHR, textStatus, errorThrown) {
            add_mod_connectslide_filter_alert(block, 'danger', jqXHR.status + " " + jqXHR.statusText);
        });
    });
}
