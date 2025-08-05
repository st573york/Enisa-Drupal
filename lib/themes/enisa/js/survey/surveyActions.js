jQuery(function($) {
  $(document).on('change', '#formFile', function (e) {
    e.preventDefault();

    $('.loader').fadeIn();

    skipFadeOut = true;

    let country_survey_id = $('#country-survey-id').val();
    let data = getFormData('upload-completed-survey');

    $.ajax({
      'url': '/country/survey/upload/' + country_survey_id,
      'type': 'POST',
      'data': data,
      'contentType': false,
      'processData': false,
      success: function () {
        viewSurvey({
          'country_survey_id': country_survey_id
        });
      },
      error: function (req) {
        $('.loader').fadeOut();

        if (req.status == 409)
        {
          explicitModal.show();
                
          let list = '<ul dusk="survey_import_alert_indicators">';
          $.each(req.responseJSON.list, function (key, val) {
            list += `<li>${val}</li>`;
          });
          list += '</ul>';
          let body =
            `<div class="alert alert-warning" role="alert">
              <h5>${req.responseJSON.message}</h5>
              <hr>
              ${list}
            </div>`;

          $('#explicit-upload-body').html(body);
          $('#explicit-filename').val(req.responseJSON.filename);
        } 
        else
        {
          let type = 'error';
          let message = '';

          if (req.status == 400)
          {
            let messages = [];
            $.each(req.responseJSON, function (key, val) {
              messages.push(val);
            });
            message = messages.join(' ');
          }
          else if (req.status == 403)
          {
            type = Object.keys(req.responseJSON)[0];
            message = req.responseJSON[type];
          }
          else if (req.status == 413) {
            message = 'The file is too large. Size should be less than or equal to 2MB';
          }

          setAlert({
            'status': type,
            'msg': message
          });
        }

        pageModal.hide();
      }
    });
  });

  $(document).on('input', '.input-choice input', function () {
    toggleAnswers(this);
    toggleReference(this);
  });
  
  $(document).on('input', '.actual-answers .form-input.master', function () {
    toggleOptions(this);
  });
});

function fillInOffline(obj)
{
  jQuery(function($) {
    $('.loader').fadeIn();

    skipFadeOut = false;
    
    $('#survey-id').val(obj.survey.id);
    $('#country-survey-id').val(obj.country_survey.id);
    
    $.ajax({
        'url': '/country/survey/offline/validate/' + obj.country_survey.id,
        success: function () {
            pageModal.show();

            $('#pageModal .alert').addClass('d-none');
        },
        error: function (req) {
            setAlert({
                'status': 'warning',
                'msg': req.responseJSON.warning
            });
        }
    });
  });
}

function toggleAnswers(e)
{
  jQuery(function($) {
    let choice = $(e).val();

    $(e).closest('.form-indicators').find('.actual-answers input').each(function (id, idx) {
      if (choice == 3) 
      {
        if ($(idx).parents().eq(1).hasClass('multiple-choice') || 
            $(idx).parents().eq(1).hasClass('single-choice')) 
        {
          $(idx).prop('checked', false);
        }
        else {
          $(idx).val('');
        }

        $(idx).prop('disabled', true);
      }
      else {
        $(idx).prop('disabled', false);
      }
    });
  });
}

function toggleReference(e)
{
  jQuery(function($) {
    let choice = $(e).val();
    let reference = $(e).closest('.form-indicators').siblings('.form-references');

    reference.toggleClass('d-none', (choice == 3));
    if (choice == 3)
    {
      reference.find('select').prop('selectedIndex', 0);
      reference.find('textarea').val('');
    }
  });
}

function toggleOptions(e)
{
  jQuery(function($) {
    $(e).closest('.form-indicators').find('.actual-answers input').each(function (id, idx) {
      if ($(e).prop('id') != $(idx).prop('id'))
      {
        if ($(e).is(':checked')) {
          $(idx).prop('checked', false);
        }
        $(idx).prop('disabled', $(e).is(':checked'));
      }
    });
  });
}