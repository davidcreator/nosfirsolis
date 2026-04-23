/**
 * Database Management Tool JavaScript
 * 
 * Handles AJAX interactions for the database management tool
 */

// Apply database migration
function applyMigration() {
  $.ajax({
    url: $('#button-migration').data('url'),
    type: 'post',
    dataType: 'json',
    data: $('#form-migration').serialize(),
    beforeSend: function() {
      $('#button-migration').button('loading');
    },
    complete: function() {
      $('#button-migration').button('reset');
    },
    success: function(json) {
      $('.alert-dismissible').remove();
      
      if (json['error']) {
        $('#form-migration').before('<div class="alert alert-danger alert-dismissible"><i class="fa fa-exclamation-circle"></i> ' + json['error'] + ' <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>');
      }
      
      if (json['success']) {
        $('#form-migration').before('<div class="alert alert-success alert-dismissible"><i class="fa fa-check-circle"></i> ' + json['success'] + ' <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>');
        
        // Update migration history
        if (json['migration_history']) {
          updateMigrationHistory(json['migration_history']);
        }
        
        // Update schema version
        if (json['schema_version']) {
          if (json['migration_needed']) {
            $('.alert-warning').html('<i class="fa fa-exclamation-triangle"></i> ' + $('#button-migration').data('migration-needed').replace('%s', json['schema_version']));
          } else {
            $('.alert-warning').replaceWith('<div class="alert alert-success"><i class="fa fa-check-circle"></i> ' + $('#button-migration').data('migration-current').replace('%s', json['schema_version']) + '</div>');
            $('#form-migration').hide();
          }
        }
      }
    },
    error: function(xhr, ajaxOptions, thrownError) {
      alert(thrownError + "\r\n" + xhr.statusText + "\r\n" + xhr.responseText);
    }
  });
}

// Validate database schema
function validateSchema() {
  $.ajax({
    url: $('#button-validate').data('url'),
    type: 'post',
    dataType: 'json',
    beforeSend: function() {
      $('#button-validate').button('loading');
    },
    complete: function() {
      $('#button-validate').button('reset');
    },
    success: function(json) {
      $('.alert-dismissible').remove();
      
      if (json['error']) {
        $('#content > .container-fluid').prepend('<div class="alert alert-danger alert-dismissible"><i class="fa fa-exclamation-circle"></i> ' + json['error'] + ' <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>');
      }
      
      if (json['success']) {
        $('#content > .container-fluid').prepend('<div class="alert alert-success alert-dismissible"><i class="fa fa-check-circle"></i> ' + json['success'] + ' <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>');
      }
      
      // Update schema errors
      if (json['schema_errors']) {
        updateSchemaErrors(json['schema_errors']);
      }
    },
    error: function(xhr, ajaxOptions, thrownError) {
      alert(thrownError + "\r\n" + xhr.statusText + "\r\n" + xhr.responseText);
    }
  });
}

// Check database integrity
function checkIntegrity() {
  $.ajax({
    url: $('#button-integrity').data('url'),
    type: 'post',
    dataType: 'json',
    beforeSend: function() {
      $('#button-integrity').button('loading');
    },
    complete: function() {
      $('#button-integrity').button('reset');
    },
    success: function(json) {
      $('.alert-dismissible').remove();
      
      if (json['error']) {
        $('#content > .container-fluid').prepend('<div class="alert alert-danger alert-dismissible"><i class="fa fa-exclamation-circle"></i> ' + json['error'] + ' <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>');
      }
      
      if (json['success']) {
        $('#content > .container-fluid').prepend('<div class="alert alert-success alert-dismissible"><i class="fa fa-check-circle"></i> ' + json['success'] + ' <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>');
      }
      
      // Update integrity issues
      if (json['integrity_issues']) {
        updateIntegrityIssues(json['integrity_issues']);
        
        // Enable or disable fix button
        if (json['integrity_issues'].length > 0) {
          $('#button-fix').prop('disabled', false);
        } else {
          $('#button-fix').prop('disabled', true);
        }
      }
    },
    error: function(xhr, ajaxOptions, thrownError) {
      alert(thrownError + "\r\n" + xhr.statusText + "\r\n" + xhr.responseText);
    }
  });
}

// Fix database issues
function fixIssues() {
  if (confirm($('#button-fix').data('confirm'))) {
    $.ajax({
      url: $('#button-fix').data('url'),
      type: 'post',
      dataType: 'json',
      beforeSend: function() {
        $('#button-fix').button('loading');
        $('#fix-result').html('');
      },
      complete: function() {
        $('#button-fix').button('reset');
      },
      success: function(json) {
        var resultHtml = '';
        
        if (json['success']) {
          resultHtml += '<div class="alert alert-success"><i class="fa fa-check-circle"></i> ' + json['success'] + '</div>';
          
          // Show fix statistics
          if (json['issues_fixed'] !== undefined && json['issues_remaining'] !== undefined) {
            resultHtml += '<div class="alert alert-info"><i class="fa fa-info-circle"></i> ' + 
                         json['issues_fixed'] + ' issues fixed, ' + 
                         json['issues_remaining'] + ' issues remaining.</div>';
          }
          
          // Show applied fixes
          if (json['applied_fixes'] && json['applied_fixes'].length > 0) {
            resultHtml += '<h4>Applied Fixes:</h4>';
            resultHtml += '<div class="table-responsive"><table class="table table-bordered table-hover">';
            resultHtml += '<thead><tr><th>Type</th><th>Table</th><th>Details</th><th>SQL</th></tr></thead><tbody>';
            
            $.each(json['applied_fixes'], function(index, fix) {
              var details = '';
              
              if (fix['type'] === 'add_column' || fix['type'] === 'modify_column' || fix['type'] === 'drop_column') {
                details = 'Column: ' + fix['column'];
              } else if (fix['type'] === 'create_index') {
                details = 'Index: ' + fix['index'];
              }
              
              resultHtml += '<tr>';
              resultHtml += '<td>' + fix['type'].replace('_', ' ') + '</td>';
              resultHtml += '<td>' + fix['table'] + '</td>';
              resultHtml += '<td>' + details + '</td>';
              resultHtml += '<td><code>' + fix['sql'] + '</code></td>';
              resultHtml += '</tr>';
            });
            
            resultHtml += '</tbody></table></div>';
          }
          
          // Show skipped dangerous operations
          if (json['skipped_dangerous'] && json['skipped_dangerous'].length > 0) {
            resultHtml += '<div class="alert alert-warning"><i class="fa fa-exclamation-triangle"></i> ' + 
                         json['skipped_dangerous_message'] + '</div>';
            
            resultHtml += '<h4>Skipped Operations (Potentially Dangerous):</h4>';
            resultHtml += '<div class="table-responsive"><table class="table table-bordered table-hover">';
            resultHtml += '<thead><tr><th>Type</th><th>Issue</th><th>SQL</th></tr></thead><tbody>';
            
            $.each(json['skipped_dangerous'], function(index, operation) {
              resultHtml += '<tr>';
              resultHtml += '<td>' + operation['type'].replace('_', ' ') + '</td>';
              resultHtml += '<td>' + operation['issue'] + '</td>';
              resultHtml += '<td><code>' + operation['sql'] + '</code></td>';
              resultHtml += '</tr>';
            });
            
            resultHtml += '</tbody></table></div>';
          }
          
          // Update integrity issues
          if (json['remaining_issues']) {
            updateIntegrityIssues(json['remaining_issues']);
            
            // Enable or disable fix button
            if (json['remaining_issues'].length > 0) {
              $('#button-fix').prop('disabled', false);
            } else {
              $('#button-fix').prop('disabled', true);
            }
          }
        }
        
        if (json['error']) {
          resultHtml += '<div class="alert alert-danger"><i class="fa fa-exclamation-circle"></i> ' + json['error'] + '</div>';
        }
        
        // Show errors if any
        if (json['errors'] && json['errors'].length > 0) {
          resultHtml += '<h4>Errors:</h4>';
          resultHtml += '<div class="table-responsive"><table class="table table-bordered table-hover">';
          resultHtml += '<thead><tr><th>Table</th><th>Type</th><th>Error</th><th>SQL</th></tr></thead><tbody>';
          
          $.each(json['errors'], function(index, error) {
            resultHtml += '<tr>';
            resultHtml += '<td>' + error['table'] + '</td>';
            resultHtml += '<td>' + error['type'].replace('_', ' ') + '</td>';
            resultHtml += '<td>' + error['message'] + '</td>';
            resultHtml += '<td><code>' + error['sql'] + '</code></td>';
            resultHtml += '</tr>';
          });
          
          resultHtml += '</tbody></table></div>';
        }
        
        // Show remaining issues if any
        if (json['remaining_issues'] && json['remaining_issues'].length > 0) {
          resultHtml += '<h4>Remaining Issues:</h4>';
          resultHtml += '<ul class="list-group">';
          
          $.each(json['remaining_issues'], function(index, issue) {
            resultHtml += '<li class="list-group-item list-group-item-warning">' + issue + '</li>';
          });
          
          resultHtml += '</ul>';
        }
        
        $('#fix-result').html(resultHtml);
      },
      error: function(xhr, ajaxOptions, thrownError) {
        alert(thrownError + "\r\n" + xhr.statusText + "\r\n" + xhr.responseText);
      }
    });
  }
}

// Update migration history display
function updateMigrationHistory(history) {
  var html = '';
  
  if (history.length > 0) {
    html += '<div class="table-responsive">';
    html += '  <table class="table table-bordered table-hover">';
    html += '    <thead>';
    html += '      <tr>';
    html += '        <th>' + $('#migration-history').data('date') + '</th>';
    html += '        <th>' + $('#migration-history').data('version') + '</th>';
    html += '        <th>' + $('#migration-history').data('description') + '</th>';
    html += '      </tr>';
    html += '    </thead>';
    html += '    <tbody>';
    
    for (var i = 0; i < history.length; i++) {
      html += '      <tr>';
      html += '        <td>' + history[i]['date_added'] + '</td>';
      html += '        <td>' + history[i]['version'] + '</td>';
      html += '        <td>' + history[i]['description'] + '</td>';
      html += '      </tr>';
    }
    
    html += '    </tbody>';
    html += '  </table>';
    html += '</div>';
  } else {
    html += '<div class="alert alert-info">' + $('#migration-history').data('no-history') + '</div>';
  }
  
  $('#migration-history').html(html);
}

// Update schema errors display
function updateSchemaErrors(errors) {
  var html = '';
  
  if (errors.length > 0) {
    html += '<div class="table-responsive">';
    html += '  <table class="table table-bordered table-hover">';
    html += '    <thead>';
    html += '      <tr>';
    html += '        <th>' + $('#schema-errors').data('table') + '</th>';
    html += '        <th>' + $('#schema-errors').data('description') + '</th>';
    html += '      </tr>';
    html += '    </thead>';
    html += '    <tbody>';
    
    for (var i = 0; i < errors.length; i++) {
      html += '      <tr>';
      html += '        <td>' + errors[i]['table'] + '</td>';
      html += '        <td>' + errors[i]['message'] + '</td>';
      html += '      </tr>';
    }
    
    html += '    </tbody>';
    html += '  </table>';
    html += '</div>';
  } else {
    html += '<div class="alert alert-success">' + $('#schema-errors').data('no-issues') + '</div>';
  }
  
  $('#schema-errors').html(html);
}

// Update integrity issues display
function updateIntegrityIssues(issues) {
  var html = '';
  
  if (issues.length > 0) {
    html += '<div class="table-responsive">';
    html += '  <table class="table table-bordered table-hover">';
    html += '    <thead>';
    html += '      <tr>';
    html += '        <th>' + $('#integrity-issues').data('table') + '</th>';
    html += '        <th>' + $('#integrity-issues').data('type') + '</th>';
    html += '        <th>' + $('#integrity-issues').data('description') + '</th>';
    html += '        <th>' + $('#integrity-issues').data('solution') + '</th>';
    html += '      </tr>';
    html += '    </thead>';
    html += '    <tbody>';
    
    for (var i = 0; i < issues.length; i++) {
      html += '      <tr>';
      html += '        <td>' + issues[i]['table'] + '</td>';
      html += '        <td>' + issues[i]['type'] + '</td>';
      html += '        <td>' + issues[i]['description'] + '</td>';
      html += '        <td>' + issues[i]['solution'] + '</td>';
      html += '      </tr>';
    }
    
    html += '    </tbody>';
    html += '  </table>';
    html += '</div>';
  } else {
    html += '<div class="alert alert-success">' + $('#integrity-issues').data('no-issues') + '</div>';
  }
  
  $('#integrity-issues').html(html);
}

// Document ready event handlers
$(document).ready(function() {
  // Button click handlers
  $('#button-migration').off('click').on('click', function() {
    applyMigration();
  });
  
  $('#button-validate').off('click').on('click', function() {
    validateSchema();
  });
  
  $('#button-integrity').off('click').on('click', function() {
    checkIntegrity();
  });
  
  $('#button-fix').off('click').on('click', function() {
    fixIssues();
  });
});