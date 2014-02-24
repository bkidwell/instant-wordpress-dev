$(function() {

var createCookie = function(name,value,days) {
    if (days) {
        var date = new Date();
        date.setTime(date.getTime()+(days*24*60*60*1000));
        var expires = '; expires='+date.toGMTString();
    }
    else var expires = '';
    document.cookie = name+'='+value+expires+'; path=/';
}

var readCookie = function(name) {
    var nameEQ = name + '=';
    var ca = document.cookie.split(';');
    for(var i=0;i < ca.length;i++) {
        var c = ca[i];
        while (c.charAt(0)==' ') c = c.substring(1,c.length);
        if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length,c.length);
    }
    return null;
}

var restoreLogin = function() {
    var savedLogin = readCookie('wpdev-login-choices');
    if(savedLogin) {
        savedLogin = savedLogin.split(',');
        $('input[name=loginas-choice][value=' + savedLogin[0] + ']').click();
        $('input[name=loginto-choice][value=' + savedLogin[1] + ']').click();
    } else {
        $('input[name=loginas-choice][value=admin]').click();
        $('input[name=loginto-choice][value=home]').click();
    }
}
restoreLogin();

var saveLogin = function() {
    var loginas = $('input[name=loginas-choice]:checked').val() || 'anonymous';
    var loginto = $('input[name=loginto-choice]:checked').val() || 'home';
    createCookie('wpdev-login-choices', loginas + ',' + loginto, 365);
}

$('input[name=loginas-choice], input[name=loginto-choice]').change(saveLogin);

$('.btn-login').click(function(event) {
    var elem = $(this);
    var instance = elem.attr('data-instance');
    var loginas = $('input[name=loginas-choice]:checked').val() || 'anonymous';
    var loginto = $('input[name=loginto-choice]:checked').val() || 'home';
    if(loginas == 'anonymous' && loginto == 'dashboard') {
        loginto = 'home';
    }
    var url = root_uri + '/instances/wp_' + loginto + '/' + instance + '/' + loginas;
    window.open(url);
});

$('.cmd-delete').click(function(event) {
    var elem = $(this);
    var instance = elem.attr('data-instance');
    $('.confirmDeleteName').text(instance);
    $('#deleteInstanceName').val(instance);
    $('#confirmDelete').modal();
    event.preventDefault();
});

var badInstanceName = false;
var notAllowed = 'docs,_system,_data'.split(',');
var isBadInstanceName = function(value) {
  return $.inArray(value, notAllowed) >= 0;
};
$('#instance').keyup(function() {
    var elem = $(this);

    if(elem.val().match(/[^a-z0-9_]/g)) {
        elem.val(elem.val().toLowerCase().replace(
            /[\-\.\,]/g, '_').replace(/[^a-z0-9_]/g, ''
        ));
    }

    if(isBadInstanceName(elem.val())) {
        if(!badInstanceName) {
            elem.tooltip({
                title: 'This name is reserved for the system.',
                trigger: 'manual'
            }).tooltip('show');
            $('#instance-submit').attr('disabled', true);
            badInstanceName = true;
        }
    } else {
        if(badInstanceName) {
            elem.tooltip('hide');
            $('#instance-submit').attr('disabled', false);
            badInstanceName = false;
        }
    }
});
$('#create-instance').submit(function(event) {
    if(isBadInstanceName($("#instance").val())) {
        event.preventDefault();
    }
});


});
