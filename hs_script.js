jQuery( document ).ready(function ($) {  



    var btnScrape = $('#btnScrape');



    var showProgress = function(index, total) {

        $('.progress').show();

        if(total > 0) {

            let percent = (index / (total * 10) ) * 100;

            $('.progress_bar').css('width', percent + '%');

            $('span', $('.progress_bar')).text(index + ' / ' + (total * 10));

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
        if($("#hs_data").val() === 'news') {
            let hs_year = $('select[name="hs_year"]').val();
            let hs_page_end = parseInt($('input[name="hs_page_end"]').val());
            let hs_page_start = parseInt($('input[name="hs_page_start"]').val());
            let queryAdded = false;
            
            if (!hs_page_end || !hs_page_start || hs_page_end < hs_page_start || hs_page_end === 0 || hs_page_start === 0) {
                alert('Please enter the page range correctly');
            } else {
                let msg = confirm('Are you sure you want to update news?');
                if (!msg) return;
                
                disableButtons(true);
                $(".progress_bar span").text('');
                showTitle('Updating News...', true);
                showProgress(0, (hs_page_end - hs_page_start + 1));
                
                let source_url = "https://scm.hsu.edu.hk/us/news/news";
                
                if (hs_year !== 'all') {
                    source_url += '?year=' + hs_year;
                    queryAdded = true;
                }
                
                let newsList = [];
                
                for (let i = hs_page_start; i <= hs_page_end; i++) {
                    let realUrl = source_url;
                    
                    if (queryAdded) {
                        realUrl += '&page=' + i;
                    } else {
                        realUrl += '?page=' + i;
                    }
                    
                    try {
                        let result = await $.ajax({
                            url: hs_script_vars.ajax_url,
                            type: 'post',
                            dataType: 'json',
                            data: {
                                action: 'hs_update_news',
                                nonce: hs_script_vars.update_news_nonce,
                                url: realUrl
                            }
                        });
                        
                        if (result.error && result.message) {
                            alert(result.message);
                            $('.progress').hide();
                        } else {
                            newsList.push(...result);
                        }
                    } catch (error) {
                        console.error(error);
                        alert('An error occurred while updating news.');
                        $('.progress').hide();
                    }
                }
                if( newsList.length) {
                    for (let k = 0; k < newsList.length; k++) {
                        try {
                            let result = await $.ajax({
                                url: hs_script_vars.ajax_url,
                                type: 'post',
                                dataType: 'json',
                                data: {
                                    action: 'hs_save_new_post',
                                    nonce: hs_script_vars.save_new_post_nonce,
                                    news: newsList[k],
                                }
                            });
                            
                            if (result.error && result.message) {
                                alert(result.message);
                                $('.progress').hide();
                            } else {
                                showProgress(k, (hs_page_end - hs_page_start + 1));
                                
                                if (k === (newsList.length - 1)) {
                                    showProgress(10, 1);
                                    setTimeout(() => {
                                        alert('Updated successfully');
                                        showProgress(0, 0);
                                        $('.progress').hide();
                                        disableButtons(false);
                                    }, 1000);
                                }
                            }
                        } catch (error) {
                            console.error(error);
                            alert('An error occurred while saving news.');
                            $('.progress').hide();
                        }
                    }
                } else {
                    alert('No news! Please select correct conditions.');
                    showProgress(0, 0);
                    $('.progress').hide();
                    disableButtons(false);
                }

            }
        } else if($("#hs_data").val() === 'events') {
            let hs_page_end = parseInt($('input[name="hs_page_end"]').val());
            let hs_page_start = parseInt($('input[name="hs_page_start"]').val());
            if (!hs_page_end || !hs_page_start || hs_page_end < hs_page_start || hs_page_end === 0 || hs_page_start === 0) {
                alert('Please enter the page range correctly');
            } else {
                let msg = confirm('Are you sure you want to update events?');
                if (!msg) return;
                disableButtons(true);
                $(".progress_bar span").text('');
                showTitle('Updating Events...', true);
                showProgress(0, (hs_page_end - hs_page_start + 1));
                
                let source_url = "https://scm.hsu.edu.hk/us/news/events";
                
                let newsList = [];
                
                for (let i = hs_page_start; i <= hs_page_end; i++) {
                    let realUrl = source_url;

                    realUrl += '?page=' + i;

                    try {
                        let result = await $.ajax({
                            url: hs_script_vars.ajax_url,
                            type: 'post',
                            dataType: 'json',
                            data: {
                                action: 'hs_update_events',
                                nonce: hs_script_vars.update_events_nonce,
                                url: realUrl
                            }
                        });
                        
                        if (result.error && result.message) {
                            alert(result.message);
                            $('.progress').hide();
                        } else {
                            newsList.push(...result);
                        }
                    } catch (error) {
                        console.error(error);
                        alert('An error occurred while updating news.');
                        $('.progress').hide();
                    }
                }
                if( newsList.length) {
                    for (let k = 0; k < newsList.length; k++) {
                        try {
                            let result = await $.ajax({
                                url: hs_script_vars.ajax_url,
                                type: 'post',
                                dataType: 'json',
                                data: {
                                    action: 'hs_save_new_event',
                                    nonce: hs_script_vars.save_new_post_nonce,
                                    news: newsList[k],
                                }
                            });
                            
                            if (result.error && result.message) {
                                alert(result.message);
                                $('.progress').hide();
                            } else {
                                showProgress(k, (hs_page_end - hs_page_start + 1));
                                
                                if (k === (newsList.length - 1)) {
                                    showProgress(10, 1);
                                    setTimeout(() => {
                                        alert('Updated successfully');
                                        showProgress(0, 0);
                                        $('.progress').hide();
                                        disableButtons(false);
                                    }, 1000);
                                }
                            }
                        } catch (error) {
                            console.error(error);
                            alert('An error occurred while saving news.');
                            $('.progress').hide();
                        }
                    }
                } else {
                    alert('No news! Please select correct conditions.');
                    showProgress(0, 0);
                    $('.progress').hide();
                    disableButtons(false);
                }
            }
        }
        // else if($("#hs_data").val() === 'staff') {
        //     let msg = confirm('Are you sure you want to update events?');
        //     if (!msg) return;
        //     disableButtons(true);
        //     $(".progress_bar span").text('');
        //     showTitle('Updating Events...', true);
        //     showProgress(0, (hs_page_end - hs_page_start + 1));
            
        //     let source_url = "https://scm.hsu.edu.hk/us/aboutus/faculty?tab=academic";
            
        //     let newsList = [];
            
        //     try {
        //         let result = await $.ajax({
        //             url: hs_script_vars.ajax_url,
        //             type: 'post',
        //             dataType: 'json',
        //             data: {
        //                 action: 'hs_update_staff',
        //                 nonce: hs_script_vars.update_staff_nonce,
        //                 url: source_url
        //             }
        //         });
                
        //         if (result.error && result.message) {
        //             alert(result.message);
        //             $('.progress').hide();
        //         } else {
        //             newsList.push(...result);
        //         }
        //     } catch (error) {
        //         console.error(error);
        //         alert('An error occurred while updating news.');
        //         $('.progress').hide();
        //     }

        //     if( newsList.length) {
        //         for (let k = 0; k < newsList.length; k++) {
        //             try {
        //                 let result = await $.ajax({
        //                     url: hs_script_vars.ajax_url,
        //                     type: 'post',
        //                     dataType: 'json',
        //                     data: {
        //                         action: 'hs_save_new_staff',
        //                         nonce: hs_script_vars.save_new_post_nonce,
        //                         news: newsList[k],
        //                     }
        //                 });
                        
        //                 if (result.error && result.message) {
        //                     alert(result.message);
        //                     $('.progress').hide();
        //                 } else {
        //                     showProgress(k, (hs_page_end - hs_page_start + 1));
                            
        //                     if (k === (newsList.length - 1)) {
        //                         showProgress(10, 1);
        //                         setTimeout(() => {
        //                             alert('Updated successfully');
        //                             showProgress(0, 0);
        //                             $('.progress').hide();
        //                             disableButtons(false);
        //                         }, 1000);
        //                     }
        //                 }
        //             } catch (error) {
        //                 console.error(error);
        //                 alert('An error occurred while saving news.');
        //                 $('.progress').hide();
        //             }
        //         }
        //     } else {
        //         alert('No news! Please select correct conditions.');
        //         showProgress(0, 0);
        //         $('.progress').hide();
        //         disableButtons(false);
        //     }
        // }

    });


    if ($("#hs_year")) {


        for(let i = 2012 ; i <= new Date().getFullYear(); i++ ) {

            $("#hs_year").prepend('<option value="'+ i +'">'+ i +'</option>');

        }
        
        $("#hs_year").prepend('<option value="all">All</option>');

        $("#hs_year").val('all');
    }

    $("#hs_data").on('change', function () {
        if($(this).val() === 'news') {
            $("#page_range").show();
            $("#news_year").show();
        } else if($(this).val() === 'events') {
            $("#page_range").show();
            $("#news_year").hide();
        }
        // else {
        //     $("#page_range").hide();
        //     $("#news_year").hide();
        // }
    });

});