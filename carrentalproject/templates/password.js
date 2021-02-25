$("#login").validate({
    errorElement: "em",
    rules: {
        email: {
            required: true,
            email: true
        },
        password: {
            required: true,
        }

    },
    submitHandler: function() {

        // Attach usefull variable with form data
        var data = $('login').serialize(),
                form_url = $('login').attr('action');
        $.ajax({
            type: 'POST',
            url: form_url,
            data: data
        })

                .done(function(data) {
                    
                    //Set cookie start
                    if ($('#remember').is(':checked')) {
                    var email = $('#email').val();//get textbox value
                    var password = $('#password').val();
                    // set cookies to expire in 14 days
                    $.cookie('email', email, { expires: 14 });
                    $.cookie('password', password, { expires: 14 });
                    $.cookie('remember', true, { expires: 14 });                
                    }
                    else
                    {//when your checkbox uncheck then set cookies as null
                        // reset cookies
                        $.cookie('email', null);
                        $.cookie('password', null);
                        $.cookie('remember', null);
                    }
                    //Set cookie end
                    // Parse the json data
                    data = $.parseJSON(data);
                    if (data.sent === true) {
                        window.location.replace("dashboard.php");
                    } 
                })

                .always(function() {


                });

    } // end submit haldler


});
