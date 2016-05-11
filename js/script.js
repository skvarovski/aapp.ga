var checkCaptcha, genCaptcha;

var clearCheck = function() {
    $('#check-captcha').popover('destroy');
    $('#check-btn i').removeClass().addClass('zmdi zmdi-arrow-right zmdi-hc-fw');
};

var clearGen = function() {
    $('#gen-captcha').popover('destroy');
    $('#generate-btn i').removeClass().addClass('zmdi zmdi-arrow-right zmdi-hc-fw');
};

var onloadCallback = function() {
    checkCaptcha = grecaptcha.render('check-captcha', {
        'sitekey' : 'XXX',
        'callback': clearCheck,
        'theme' : 'dark'
    });
    genCaptcha = grecaptcha.render('gen-captcha', {
        'sitekey' : 'XXX',
        'callback': clearGen,
        'theme' : 'dark'
    });
};

$(document).ready(function() {
    $.material.init();

    if (Cookies.get('name'))
        $('#name').val(Cookies.get('name'));
    if (Cookies.get('email'))
        $('#email').val(Cookies.get('email'));
    if (Cookies.get('password'))
        $('#password').val(Cookies.get('password'));
    if (Cookies.get('expire'))
        $('#expire').val(Cookies.get('expire'));
    if (Cookies.get('zip_name'))
        $('#download').html('<a href="playlists/' + Cookies.get('zip_name') + '" download><i class="zmdi zmdi-archive"></i>' + Cookies.get('zip_name') + '</a>').css('display', 'inline-block');

    if (Cookies.getJSON('generate_form')) {
        var generate_form = Cookies.getJSON('generate_form');
        $.each(generate_form, function(key, value){
            $('#generate-form input[value="' + key + '"]').prop('checked', value);
        });
    }

    if ((!!$('#email').val().trim()) && (!!$('#password').val().trim())) {
        $('#options fieldset').prop('disabled', false);
    }

    $('.navbar-collapse ul li a').click(function () {
        $('.navbar-toggle:visible').click();
    });

    $('nav a').on('click', function (e) {
        e.preventDefault();

        $('.nav').find('.active').removeClass('active');
        if ($(this).attr('class') != 'navbar-brand')
            $(this).parent().addClass('active');

        $('#navbar').removeClass('spy-active');

        var hash = this.hash;

        $('html, body').animate({
            scrollTop: $(hash).offset().top - 70
        }, 300, function () {
            if (hash == '#page-top')
                window.location.hash = '';
            else
                window.location.hash = hash;
            $('#navbar').addClass('spy-active');
        });
    });

    $('#generate-form').validate({
        rules: {
            "station[]": {
                required: true,
                minlength: 1
            },
            "quality[]": {
                required: true,
                minlength: 1
            },
            "format[]": {
                required: true,
                minlength: 1
            },
            "server[]": {
                required: true,
                minlength: 1
            }
        },
        messages: {
            "station[]": "Select at least one radio station",
            "quality[]": "Select at least one audio quality",
            "format[]": "Select at least one playlist format",
            "server[]": "Select at least one server"
        },
        errorPlacement: function(error, element) {
            var label = element.parents().eq(1).prev();
            label.addClass('has-error').popover({ content: error.text() }).popover('show');
        }
    });

    $('#email, #password, #gen-captcha').focus(function(){
        $(this).popover('destroy');
        $('#check-btn i').removeClass().addClass('zmdi zmdi-arrow-right zmdi-hc-fw');
    });

    $('#email, #password').blur(function(){
        if ((!!$('#email').val().trim()) && (!!$('#password').val().trim())) {
            $('#options fieldset').prop('disabled', false);
        } else {
            $('#options fieldset').prop('disabled', true);
        }
    });

    $('#generate input[type="checkbox"]').change(function(){
        $(this).parents().eq(1).prevAll().eq(1).removeClass('has-error').popover('destroy');
        $('#generate-btn i').removeClass().addClass('zmdi zmdi-arrow-right zmdi-hc-fw');
    });

    $('#check-form').on('submit',function(e){
        e.preventDefault();
        $('#check-btn i').removeClass('zmdi-arrow-right').addClass('zmdi-spinner zmdi-hc-spin');
        $.ajax({
                type: 'POST',
                url: 'check.php',
                data: $('#check-form').serialize(),
                dataType: 'json'
            })
            .done(function (data) {
                if (!data.success) {
                    $('#name, #expire').val('');
                    if (data.errors.email) {
                        $('#email-group').addClass('has-error');
                        $('#email').popover({ content: data.errors.email }).popover('show');
                    }
                    if (data.errors.password) {
                        $('#password-group').addClass('has-error');
                        $('#password').popover({ content: data.errors.password }).popover('show');
                    }
                    if (data.errors.invalid) {
                        $('#email-group, #password-group').addClass('has-error');
                        $('#email, #password').popover({ content: data.errors.invalid }).popover('show');
                    }
                    if (data.errors.captcha) {
                        $('#check-captcha').popover({ content: data.errors.captcha }).popover('show');
                    }
                    $('#check-btn i').removeClass('zmdi-spinner zmdi-hc-spin').addClass('zmdi-close');
                } else {
                    $('#email-group, #password-group').removeClass('has-error');
                    $('#email, #password, #check-captcha').popover('destroy');
                    $('#name').val(data.name);
                    $('#expire').val(data.expire + ', ' + data.status);

                    Cookies.set('name', data.name, { expires: 365 });
                    Cookies.set('email', $('#email').val(), { expires: 365 });
                    Cookies.set('password', $('#password').val(), { expires: 365 });
                    Cookies.set('expire', data.expire, { expires: 365 });

                    if (data.status == 'expired') {
                        $('#check-btn i').removeClass('zmdi-spinner zmdi-hc-spin').addClass('zmdi-calendar-close');
                    } else {
                        $('#check-btn i').removeClass('zmdi-spinner zmdi-hc-spin').addClass('zmdi-check');
                    }
                }
                grecaptcha.reset(checkCaptcha);
            });
    });

    $('#generate-form').on('submit',function(e){
        if ($('#generate-form').valid()) {
            e.preventDefault();
            $('#generate-btn i').removeClass('zmdi-arrow-right').addClass('zmdi-spinner zmdi-hc-spin');
            $.ajax({
                    type: 'POST',
                    url: 'generate.php',
                    data: $('#generate-form, #check-form input').serialize(),
                    dataType: 'json'
                })
                .done(function (data) {
                    if (!data.success) {
                        if (data.errors.station)
                            $('#station').addClass('has-error').popover({ content: data.errors.station })
                                .popover('show');
                        if (data.errors.quality)
                            $('#quality').addClass('has-error').popover({ content: data.errors.quality })
                                .popover('show');
                        if (data.errors.format)
                            $('#format').addClass('has-error').popover({ content: data.errors.format })
                                .popover('show');
                        if (data.errors.server)
                            $('#server').addClass('has-error').popover({ content: data.errors.server })
                                .popover('show');
                        if (data.errors.account)
                            $('#generate-btn').popover({ content: data.errors.account })
                                .popover('show');
                        if (data.errors.captcha)
                            $('#gen-captcha').popover({ content: data.errors.captcha }).popover('show');
                        $('#generate-btn i').removeClass('zmdi-spinner zmdi-hc-spin').addClass('zmdi-close');
                    } else {
                        $('#download').fadeOut().html('<a href="playlists/' + data.zip_name + '" download><i class="zmdi zmdi-archive"></i>' + data.zip_name + '</a>').fadeIn().css('display', 'inline-block');
                        Cookies.set('zip_name', data.zip_name, { expires: 365 });

                        var $formData = {};
                        $('#generate-form input').each(function(){
                            $formData[$(this).val()] = $(this).prop('checked');
                        });
                        Cookies.set('generate_form', $formData);

                        if (data.name) {
                            $('#name').val(data.name);
                            Cookies.set('name', data.name, { expires: 365 });
                        }
                        if (data.email) {
                            $('#email').val(data.email);
                            Cookies.set('email', data.email, { expires: 365 });
                        }
                        if (data.password) {
                            $('#password').val(data.password);
                            Cookies.set('password', data.password, { expires: 365 });
                        }
                        if (data.expire) {
                            $('#expire').val(data.expire + ', ' + data.status);
                            Cookies.set('expire', data.expire, { expires: 365 });
                        }

                        $('#generate-btn i').removeClass('zmdi-spinner zmdi-hc-spin').addClass('zmdi-check');

                        $('#download a')[0].click();

                        $('#options fieldset').prop('disabled', false);
                    }
                    grecaptcha.reset(genCaptcha);
                });
        }
    });
});
