jQuery( document ).ready(function ($) {  



    var btnScrape = $('#btnScrape');



    var showProgress = function(index, total) {

        $('.progress').show();

        if(total > 0) {

            let percent = (index / (total) ) * 100;

            $('.progress_bar').css('width', percent + '%');

            $('span', $('.progress_bar')).text(index + ' / ' + (total));

        } else {

            $('.progress_bar').css('width', '0px');

        }

    }



    var showTitle = function(title, show) {

        $('#title').text(title);

        if(show) {

            $('#title').show();

        } else {

            $('#title').hide();

        }

    }



    var disableButtons = function(disabled) {

        if(disabled) {

            $('button').addClass('disabled').prop('disabled', true);            

        } else {

            $('button').removeClass('disabled').prop('disabled', false);            

            showTitle('', false);

        }

    }



    btnScrape.on('click', async function() {


        disableButtons(true);
        $(".progress_bar span").text('');
        showTitle('Updating News...', true);
        showProgress(0, 1);
        
        let result = await $.ajax({
            url: hs_script_vars.ajax_url,
            type: 'get',
            dataType: 'json',
            data: {
                action: 'hs_update_news',
                nonce: hs_script_vars.update_news_nonce,
            }
        });
        
        if (result.error && result.message) {
            alert(result.message);
            $('.progress').hide();
        } else {
            showProgress(1, 1);
            alert("Updated successfully!");
        }
    });

});