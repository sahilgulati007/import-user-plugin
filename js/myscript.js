jQuery(document).ready(function($) {
    jQuery("#formcsv").on('submit', function(e)
    {
        e.preventDefault();
        jQuery(".loader").css("display", "block");
        // alert('in');
        //$this = $(this);
        // file_data = $(this).prop('csv_file')[0];
        // form_data = new FormData(this);
        // // form_data.append('csv_file', file_data);
        // form_data.append('action', 'my_action');
        // var data = form_data;

        jQuery.ajax({
                url:ajaxurl,
                type:"POST",
                processData: false,
                contentType: false,
                data:  new FormData(this),
                success : function( response ){
                    if(response == "done"){
                        alert('File uploaded!');
                    }else{
                        alert(returnedData.msg);
                    }
                    jQuery(".loader").css("display", "none");
                },
            });

        // jQuery.post(ajaxurl, data, function(response) {
        //     alert('Got this from the server: ' + response);
        // });
    });
});