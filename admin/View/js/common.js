/**
 * Common JavaScript Utilities - Improved Version
 * ============================================
 * 
 * MAINTENANCE INDEX:
 * 1. URL Utilities (Lines 32-48)
 * 2. Browser Detection (Lines 51-60)
 * 3. Header/Modal Management (Lines 63-116)
 * 4. Menu Management (Lines 119-171)
 * 5. UI Components (Lines 174-292)
 * 6. Form Handling (Lines 295-507)
 * 7. File Upload System (Lines 510-721)
 * 8. File Manager System (Lines 724-1358)
 * 9. Image Manager (Lines 1361-1409)
 * 10. Chain AJAX Handler (Lines 1412-1490)
 * 11. Autocomplete Plugin (Lines 1493-1680)
 * 12. Button State Plugin (Lines 1683-1727)
 * 13. Modal Fix (Lines 1731-1790)
 * 
 * MAJOR IMPROVEMENTS MADE:
 * - Removed sessionStorage usage (not supported in artifacts)
 * - Added proper error handling and validation
 * - Improved memory management and event cleanup
 * - Added JSDoc documentation
 * - Fixed variable scoping issues
 * - Added debouncing for better performance
 * - Improved accessibility features
 */

'use strict';

// ===================
// 1. URL UTILITIES
// ===================

/**
 * Extract URL parameter value by key
 * @param {string} key - The parameter name to extract
 * @returns {string} The parameter value or empty string
 */
function getURLVar(key) {
    try {
        const urlParams = new URLSearchParams(window.location.search);
        return urlParams.get(key) || '';
    } catch (error) {
        console.warn('Error parsing URL parameters:', error);
        return '';
    }
}

// ===================
// 2. BROWSER DETECTION
// ===================

/**
 * Detect Internet Explorer browser
 * @returns {boolean} True if IE is detected
 */
function isIE() {
    return !!(window.ActiveXObject || "ActiveXObject" in window);
}

// ===================
// 3. HEADER/MODAL MANAGEMENT
// ===================

$(document).ready(function() {
    /**
     * Handle header notification modals
     */
    $('#header-notification [data-bs-toggle="modal"]').on('click', function(e) {
        e.preventDefault();
        
        const element = this;
        const url = $(element).attr('href');
        
        if (!url) {
            console.error('No URL specified for modal');
            return;
        }

        // Remove existing modal
        $('#modal-notification').remove();

        $.ajax({
            url: url,
            dataType: 'html',
            timeout: 10000,
            success: function(html) {
                if (html) {
                    $('body').append(html);
                    $('#modal-notification').modal('show');
                }
            },
            error: function(xhr, status, error) {
                console.error('Failed to load modal:', error);
                // Show user-friendly error message
                $('body').append(`
                    <div class="modal fade" id="modal-error" tabindex="-1">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Error</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <p>Failed to load content. Please try again.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                `);
                $('#modal-error').modal('show');
            }
        });
    });
});

// ===================
// 4. MENU MANAGEMENT
// ===================

$(document).ready(function() {
    let currentMenuPath = '';

    /**
     * Toggle mobile menu
     */
    $('#button-menu').on('click', function(e) {
        e.preventDefault();
        $('#column-left').toggleClass('active');
    });

    /**
     * Handle menu navigation with in-memory state tracking
     */
    $('#menu a[href]').on('click', function() {
        const href = $(this).attr('href');
        if (href) {
            currentMenuPath = href;
            updateMenuState(href);
        }
    });

    /**
     * Update menu active states
     * @param {string} path - The menu path to activate
     */
    function updateMenuState(path) {
        // Remove all active states
        $('#menu .active').removeClass('active');
        $('#menu .show').removeClass('show');
        $('#menu .collapsed').addClass('collapsed');

        if (path) {
            const $targetLink = $(`#menu a[href="${path}"]`);
            if ($targetLink.length) {
                // Set active states
                $targetLink.parent().addClass('active');
                $targetLink.parents('li').children('a').removeClass('collapsed');
                $targetLink.parents('ul').addClass('show');
                $targetLink.parents('li').addClass('active');
            }
        } else {
            // Default to dashboard if no path
            $('#menu #menu-dashboard').addClass('active');
        }
    }

    // Initialize menu state
    updateMenuState(currentMenuPath);
});

// ===================
// 5. UI COMPONENTS
// ===================

$(document).ready(function() {
    /**
     * Initialize tooltips with proper cleanup
     */
    function initTooltip() {
        try {
            const tooltip = bootstrap.Tooltip.getOrCreateInstance(this);
            if (tooltip) {
                tooltip.show();
            }
        } catch (error) {
            console.warn('Tooltip initialization failed:', error);
        }
    }

    $(document).on('mouseenter', '[data-bs-toggle="tooltip"]', initTooltip);

    // Clean up tooltips on button clicks
    $(document).on('click', 'button', function() {
        $('.tooltip').remove();
    });

    /**
     * Date picker initialization with error handling
     */
    function initDatePicker() {
        try {
            $(this).daterangepicker({
                singleDatePicker: true,
                autoApply: true,
                autoUpdateInput: false,
                locale: {
                    format: 'YYYY-MM-DD'
                }
            }, function(start) {
                if (start?.isValid()) {
                    $(this.element).val(start.format('YYYY-MM-DD'));
                }
            });
        } catch (error) {
            console.error('Date picker initialization failed:', error);
        }
    }

    $(document).on('focus', '.date', initDatePicker);

    /**
     * Time picker initialization
     */
    function initTimePicker() {
        try {
            $(this).daterangepicker({
                singleDatePicker: true,
                datePicker: false,
                autoApply: true,
                autoUpdateInput: false,
                timePicker: true,
                timePicker24Hour: true,
                locale: {
                    format: 'HH:mm'
                }
            }, function(start) {
                if (start && start.isValid()) {
                    $(this.element).val(start.format('HH:mm'));
                }
            }).on('show.daterangepicker', function(ev, picker) {
                if (picker && picker.container) {
                    picker.container.find('.calendar-table').hide();
                }
            });
        } catch (error) {
            console.error('Time picker initialization failed:', error);
        }
    }

    $(document).on('focus', '.time', initTimePicker);

    /**
     * DateTime picker initialization
     */
    function initDateTimePicker() {
        try {
            $(this).daterangepicker({
                singleDatePicker: true,
                autoApply: true,
                autoUpdateInput: false,
                timePicker: true,
                timePicker24Hour: true,
                locale: {
                    format: 'YYYY-MM-DD HH:mm'
                }
            }, function(start) {
                if (start && start.isValid()) {
                    $(this.element).val(start.format('YYYY-MM-DD HH:mm'));
                }
            });
        } catch (error) {
            console.error('DateTime picker initialization failed:', error);
        }
    }

    $(document).on('focus', '.datetime', initDateTimePicker);

    /**
     * Auto-hide alerts with improved timing
     */
    function fadeAlert() {
        setTimeout(function() {
            $('.alert-dismissible').fadeTo(1000, 0, function() {
                $(this).remove();
            });
        }, 6000);
    }

    $(document).on('click', 'button', fadeAlert);
});

// ===================
// 6. FORM HANDLING
// ===================

/**
 * Enhanced form submission with better error handling
 */
$(document).on('submit', 'form', function(e) {
    const form = this;
    const $form = $(form);
    
    // Determine the submitter button
    let button = '';
    
    if (e.originalEvent && e.originalEvent.submitter) {
        button = e.originalEvent.submitter;
    }

    // Check if AJAX handling is required
    const formAjax = $form.attr('data-rms-toggle');
    const buttonAjax = button ? $(button).attr('data-rms-toggle') : '';
    
    if (formAjax !== 'ajax' && buttonAjax !== 'ajax') {
        return; // Allow normal form submission
    }

    e.preventDefault();

    // Validate form before submission
    if (!validateForm(form)) {
        return;
    }

    // Get form attributes with defaults
    let action = $form.attr('action') || window.location.href;
    let method = $form.attr('method') || 'post';
    let enctype = $form.attr('enctype') || 'application/x-www-form-urlencoded';

    // Handle button overrides
    if (button) {
        const formaction = $(button).attr('formaction');
        const formmethod = $(button).attr('formmethod');
        const formenctype = $(button).attr('formenctype');
        
        if (formaction) action = formaction;
        if (formmethod) method = formmethod;
        if (formenctype) enctype = formenctype;
    }

    // Update CKEditor instances if available
    if (typeof CKEDITOR !== 'undefined') {
        try {
            for (const instance in CKEDITOR.instances) {
                if (CKEDITOR.instances.hasOwnProperty(instance)) {
                    CKEDITOR.instances[instance].updateElement();
                }
            }
        } catch (error) {
            console.warn('CKEditor update failed:', error);
        }
    }

    // Perform AJAX request
    $.ajax({
        url: action.replace(/&amp;/g, '&'),
        type: method.toUpperCase(),
        data: $form.serialize(),
        dataType: 'text',
        timeout: 30000,
        beforeSend: function() {
            if (button) {
                $(button).button('loading');
            }
            // Disable form to prevent double submission
            $form.find('input, button, select, textarea').prop('disabled', true);
        },
        complete: function() {
            if (button) {
                $(button).button('reset');
            }
            // Re-enable form
            $form.find('input, button, select, textarea').prop('disabled', false);
        },
        success: function(data) {
            let json = data;

            if (typeof data === 'string') {
                try {
                    json = JSON.parse(data);
                } catch (e) {
                    console.error('Form submission failed: Invalid JSON response.', e);
                    console.error('Server response:', data);
                    showAlert('danger', 'An unexpected error occurred. The server returned an invalid response.');

                    return;
                }
            }

            handleFormResponse(json, form);
        },
        error: function(xhr, status, error) {
            if (xhr && xhr.status === 200 && xhr.responseText) {
                try {
                    handleFormResponse(JSON.parse(xhr.responseText), form);
                    return;
                } catch (parseError) {
                    console.error('Form submission fallback parse failed:', parseError);
                }
            }

            console.error('Form submission failed:', status, error);
            if (xhr && xhr.responseText) {
                console.error('Server response:', xhr.responseText);
            }
            showAlert('danger', 'Request failed. Please try again.');
        }
    });
});

/**
 * Validate form before submission
 * @param {HTMLFormElement} form - The form to validate
 * @returns {boolean} True if form is valid
 */
function validateForm(form) {
    const $form = $(form);
    let isValid = true;

    // Clear previous validation states
    $form.find('.is-invalid').removeClass('is-invalid');
    $form.find('.invalid-feedback').removeClass('d-block');

    // Basic required field validation
    $form.find('[required]').each(function() {
        if (!$(this).val().trim()) {
            $(this).addClass('is-invalid');
            isValid = false;
        }
    });

    return isValid;
}

/**
 * Handle form response from server
 * @param {Object} json - Server response
 * @param {HTMLFormElement} form - The form element
 */
function handleFormResponse(json, form) {
    const $form = $(form);

    // Clear previous alerts and validation states
    $('.alert-dismissible').remove();
    $form.find('.is-invalid').removeClass('is-invalid');
    $form.find('.invalid-feedback').removeClass('d-block');

    // Handle redirect
    if (json.redirect) {
        window.location.href = json.redirect;
        return;
    }

    // Handle errors
    if (json.error) {
        if (typeof json.error === 'string') {
            showAlert('danger', json.error);
        } else if (typeof json.error === 'object') {
            if (json.error.warning) {
                showAlert('danger', json.error.warning);
            }

            // Handle field-specific errors
            for (const key in json.error) {
                if (json.error.hasOwnProperty(key) && key !== 'warning') {
                    const fieldId = key.replace(/_/g, '-');
                    $(`#input-${fieldId}`).addClass('is-invalid')
                        .find('.form-control, .form-select, .form-check-input, .form-check-label')
                        .addClass('is-invalid');
                    $(`#error-${fieldId}`).html(json.error[key]).addClass('d-block');
                }
            }
        }
    }

    // Handle success
    if (json.success) {
        showAlert('success', json.success);

        // Handle auto-refresh
        const url = $form.attr('data-rms-load');
        const target = $form.attr('data-rms-target');

        if (url && target) {
            $(target).load(url);
        }
    }

    // Update form values
    for (const key in json) {
        if (json.hasOwnProperty(key) && key !== 'error' && key !== 'success' && key !== 'redirect') {
            $form.find(`[name="${key}"]`).val(json[key]);
        }
    }
}

/**
 * Show alert message
 * @param {string} type - Alert type (success, danger, warning, info)
 * @param {string} message - Alert message
 */
function showAlert(type, message) {
    const iconMap = {
        success: 'fa-circle-check',
        danger: 'fa-circle-exclamation',
        warning: 'fa-triangle-exclamation',
        info: 'fa-circle-info'
    };

    const icon = iconMap[type] || 'fa-circle-info';
    const alertHtml = `
        <div class="alert alert-${type} alert-dismissible" data-testid="admin-alert" data-alert-type="${type}">
            <i class="fa-solid ${icon}"></i> ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;

    $('#alert').prepend(alertHtml);
}

// ===================
// 7. FILE UPLOAD SYSTEM
// ===================

/**
 * Enhanced file upload with validation and progress
 */
$(document).on('click', '[data-rms-toggle="upload"]', function(e) {
    e.preventDefault();
    
    const element = this;
    const $element = $(element);
    
    if ($element.prop('disabled')) {
        return;
    }

    // Remove existing upload form
    $('#form-upload').remove();

    // Create new upload form
    $('body').prepend(`
        <form enctype="multipart/form-data" id="form-upload" style="display: none;">
            <input type="file" name="file" />
        </form>
    `);

    const $fileInput = $('#form-upload input[name="file"]');
    $fileInput.trigger('click');

    // File validation
    $fileInput.on('change', function() {
        const file = this.files[0];
        if (!file) return;

        const maxSize = parseInt($element.attr('data-rms-size-max')) || 0;
        const sizeError = $element.attr('data-rms-size-error') || 'File size exceeds limit';

        if (maxSize > 0 && (file.size / 1024) > maxSize) {
            alert(sizeError);
            $(this).val('');
            return;
        }

        // Validate file type if specified
        const allowedTypes = $element.attr('data-rms-types');
        if (allowedTypes) {
            const types = allowedTypes.split(',').map(t => t.trim().toLowerCase());
            const fileExt = file.name.split('.').pop().toLowerCase();
            
            if (!types.includes(fileExt)) {
                alert('Tipo de arquivo inválido. Tipos permitidos: ' + allowedTypes);
                $(this).val('');
                return;
            }
        }
        
        // Verificar se o arquivo está vazio
        if (file.size === 0) {
            alert('O arquivo está vazio. Por favor, selecione um arquivo válido.');
            $(this).val('');
            return;
        }

        // Proceed with upload
        uploadFile();
    });

    function uploadFile() {
        const url = $element.attr('data-rms-url');
        if (!url) {
            console.error('No upload URL specified');
            alert('Erro: URL de upload não especificada');
            return;
        }

        $.ajax({
            url: url,
            type: 'post',
            data: new FormData($('#form-upload')[0]),
            dataType: 'json',
            cache: false,
            contentType: false,
            processData: false,
            timeout: 60000,
            beforeSend: function() {
                $element.button('loading');
                // Adicionar indicador visual de progresso
                if (!$('#upload-progress').length) {
                    $element.after('<div id="upload-progress" class="progress mt-2" style="display:none;"><div class="progress-bar" role="progressbar" style="width: 0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">0%</div></div>');
                }
                $('#upload-progress').show();
            },
            xhr: function() {
                const xhr = new window.XMLHttpRequest();
                xhr.upload.addEventListener('progress', function(e) {
                    if (e.lengthComputable) {
                        const percentComplete = Math.round((e.loaded / e.total) * 100);
                        $('#upload-progress .progress-bar').width(percentComplete + '%');
                        $('#upload-progress .progress-bar').text(percentComplete + '%');
                        $('#upload-progress .progress-bar').attr('aria-valuenow', percentComplete);
                    }
                }, false);
                return xhr;
            },
            complete: function() {
                $element.button('reset');
                // Esconder a barra de progresso após um breve delay
                setTimeout(function() {
                    $('#upload-progress').hide();
                }, 2000);
            },
            success: function(json) {
                console.log('Upload response:', json);

                if (json.error) {
                    alert(json.error);
                    return;
                }

                if (json.success) {
                    alert(json.success);
                }

                if (json.code) {
                    const target = $element.attr('data-rms-target');
                    if (target) {
                        $(target).val(json.code);
                        $element.parent()
                            .find('[data-rms-toggle="download"], [data-rms-toggle="clear"]')
                            .prop('disabled', false);
                    }
                }
            },
            error: function(xhr, status, error) {
                console.error('Upload failed:', {
                    xhr: xhr,
                    status: status,
                    error: error
                });

                let errorMessage = 'Falha no upload. Por favor, tente novamente.';
                if (xhr.responseJSON && xhr.responseJSON.error) {
                    errorMessage = xhr.responseJSON.error;
                } else if (xhr.responseText) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        if (response.error) {
                            errorMessage = response.error;
                        }
                    } catch (e) {
                        // Not a JSON response, use the full text if it's short
                        if (xhr.responseText.length < 200) {
                            errorMessage = xhr.responseText;
                        }
                    }
                }
                
                // Log do erro para depuração
                console.log('Erro detalhado:', errorMessage);

                alert(errorMessage);
            }
        });
    }
});

/**
 * Handle file downloads
 */
$(document).on('click', '[data-rms-toggle="download"]', function() {
    const element = this;
    const target = $(element).attr('data-rms-target');
    const value = target ? $(target).val() : '';

    if (value) {
        const userToken = getURLVar('user_token');
        const downloadUrl = `index.php?route=tool/upload.download&user_token=${userToken}&code=${value}`;
        window.location.href = downloadUrl;
    }
});

/**
 * Clear uploaded files
 */
$(document).on('click', '[data-rms-toggle="clear"]', function() {
    const element = this;
    const $element = $(element);
    const target = $element.attr('data-rms-target');
    const thumb = $element.attr('data-rms-thumb');

    // Clear image thumbnail
    if (thumb) {
        const $thumb = $(thumb);
        const placeholder = $thumb.attr('data-rms-placeholder');
        if (placeholder) {
            $thumb.attr('src', placeholder);
        }
    }

    // Disable download/clear buttons
    const $downloadBtn = $element.parent().find('[data-rms-toggle="download"]');
    if ($downloadBtn.length) {
        $element.parent()
            .find('[data-rms-toggle="download"], [data-rms-toggle="clear"]')
            .prop('disabled', true);
    }

    // Clear target value
    if (target) {
        $(target).val('');
    }
});

// =======================
// 8. FILE MANAGER SYSTEM
// =======================
/**
 * ReamurCMS FileManager
 * Gerenciador de arquivos para upload e manipulação de imagens
 */
var FileManager = (function() {
    'use strict';
    
    var config = {};
    var debugMode = false; // Altere para true para habilitar debug
    
    /**
     * Função de debug
     */
    function debugLog(message, data) {
        if (debugMode) {
            console.log('[FileManager Debug] ' + message, data || '');
        }
    }
    
    /**
     * Inicialização do FileManager
     */
    function init() {
        config = window.fileManagerConfig || {};
        
        // Sanitiza traduções
        sanitizeTranslations();
        
        debugLog('FileManager iniciado');
        debugLog('User token: ' + config.userToken);
        debugLog('Config file max size: ' + config.maxFileSize + ' KB');
        
        // Carrega lista inicial
        loadFileList();
        
        // Registra event listeners
        registerEventListeners();
    }
    
    /**
     * Remove caracteres problemáticos das traduções
     */
    function sanitizeTranslations() {
        if (config.translations) {
            Object.keys(config.translations).forEach(function(key) {
                if (typeof config.translations[key] === 'string') {
                    config.translations[key] = config.translations[key].replace(/[\r\n\t]/g, ' ').trim();
                }
            });
        }
    }
    
    /**
     * Carrega a lista inicial de arquivos
     */
    function loadFileList() {
        $('#modal-image .modal-body').load('index.php?route=common/filemanager.list&user_token=' + config.userToken);
    }
    
    /**
     * Registra todos os event listeners
     */
    function registerEventListeners() {
        // Navegação
        registerNavigationEvents();
        
        // Busca
        registerSearchEvents();
        
        // Upload
        registerUploadEvents();
        
        // Gerenciamento de pastas
        registerFolderEvents();
        
        // Exclusão
        registerDeleteEvents();
        
        // Seleção de imagens
        registerSelectionEvents();
        
        // Paginação
        registerPaginationEvents();
    }
    
    /**
     * Event listeners para navegação
     */
    function registerNavigationEvents() {
        $('#modal-image').on('click', '#button-parent', function (e) {
            e.preventDefault();
            $('#modal-image .modal-body').load($(this).attr('href'));
        });

        $('#modal-image').on('click', '#button-refresh', function (e) {
            e.preventDefault();
            refreshFileList($(this).attr('href'));
        });

        $('#modal-image').on('click', 'a.directory', function (e) {
            e.preventDefault();
            $('#modal-image .modal-body').load($(this).attr('href'));
        });
    }
    
    /**
     * Event listeners para busca
     */
    function registerSearchEvents() {
        $('#modal-image').on('keydown', '#input-search', function (e) {
            if (e.which == 13) {
                $('#button-search').trigger('click');
            }
        });

        $('#modal-image').on('click', '#button-search', function (e) {
            performSearch();
        });
    }
    
    /**
     * Event listeners para upload
     */
    function registerUploadEvents() {
        $('#modal-image').on('click', '#button-upload', function () {
            initFileUpload();
        });
    }
    
    /**
     * Event listeners para gerenciamento de pastas
     */
    function registerFolderEvents() {
        $('#modal-image').on('click', '#button-folder', function () {
            $('#modal-folder').slideToggle();
        });

        $('#modal-image').on('click', '#button-create', function () {
            createFolder();
        });
    }
    
    /**
     * Event listeners para exclusão
     */
    function registerDeleteEvents() {
        $('#modal-image').on('click', '#button-delete', function (e) {
            deleteSelectedFiles();
        });
    }
    
    /**
     * Event listeners para seleção de imagens
     */
    function registerSelectionEvents() {
        $('#modal-image').on('click', 'a.thumbnail', function (e) {
            e.preventDefault();
            selectImage($(this));
        });
    }
    
    /**
     * Event listeners para paginação
     */
    function registerPaginationEvents() {
        $('#modal-image').on('click', '.pagination a', function (e) {
            e.preventDefault();
            $('#modal-image .modal-body').load($(this).attr('href'));
        });
    }
    
    /**
     * Atualiza a lista de arquivos com cache busting
     */
    function refreshFileList(href) {
        debugLog('Botão refresh clicado');
        
        var timestamp = new Date().getTime();
        var refreshUrl = href + '&_=' + timestamp;
        
        debugLog('URL de refresh:', refreshUrl);
        
        $('#modal-image .modal-body').load(refreshUrl, function(response, status, xhr) {
            debugLog('Refresh concluído. Status:', status);
            if (status === 'error') {
                debugLog('Erro no refresh:', xhr.status + ' ' + xhr.statusText);
            }
            
            updateImageThumbnails(timestamp);
        });
    }
    
    /**
     * Força atualização de thumbnails
     */
    function updateImageThumbnails(timestamp) {
        var imageCount = 0;
        $('#modal-image .modal-body img').each(function() {
            imageCount++;
            var src = $(this).attr('src');
            if (src && src.indexOf('?') === -1) {
                $(this).attr('src', src + '?_=' + timestamp);
            }
        });
        debugLog('Imagens atualizadas:', imageCount);
    }
    
    /**
     * Executa busca de arquivos
     */
    function performSearch() {
        var url = 'index.php?route=common/filemanager.list&user_token=' + config.userToken;
        var directory = $('#input-directory').val();

        if (directory) {
            url += '&directory=' + encodeURIComponent(directory);
        }

        var filter_name = $('#input-search').val();
        if (filter_name) {
            url += '&filter_name=' + encodeURIComponent(filter_name);
        }

        if (config.thumb) {
            url += '&thumb=' + encodeURIComponent(config.thumb);
        }

        if (config.target) {
            url += '&target=' + encodeURIComponent(config.target);
        }

        if (config.ckeditor) {
            url += '&ckeditor=' + encodeURIComponent(config.ckeditor);
        }

        $('#modal-image .modal-body').load(url);
    }
    
    /**
     * Inicializa o processo de upload de arquivos
     */
    function initFileUpload() {
        $('#form-upload').remove();
        $('body').prepend('<form enctype="multipart/form-data" id="form-upload" style="display: none;"><input type="file" name="file" accept="image/*" value="" multiple="multiple"/></form>');
        
        $('#form-upload input[name=\'file\']').trigger('click');
        $('#form-upload input[name=\'file\']').on('change', handleFileSelection);
    }
    
    /**
     * Manipula seleção de arquivos para upload
     */
    function handleFileSelection() {
        var files = this.files;
        if (!validateFiles(files)) {
            return;
        }
        
        if (files.length > 1) {
            uploadMultipleFiles(files);
        } else {
            uploadSingleFile();
        }
    }
    
    /**
     * Valida arquivos selecionados
     */
    function validateFiles(files) {
        var allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        var allowedExtensions = ['.jpg', '.jpeg', '.png', '.gif', '.webp'];
        
        for (var i = 0; i < files.length; i++) {
            var file = files[i];
            var fileName = file.name.toLowerCase();
            var fileExtension = fileName.substring(fileName.lastIndexOf('.'));
            
            // Verifica caracteres especiais no nome
            if (/[\s\(\)\[\]\{\}]/.test(file.name)) {
                $('#form-upload input[name=\'file\']').val('');
                alert('Nome do arquivo não pode conter espaços ou caracteres especiais. Use apenas letras, números, hífen e underscore.');
                return false;
            }
            
            // Verifica tamanho
            if ((file.size / 1024) > config.maxFileSize) {
                $('#form-upload input[name=\'file\']').val('');
                alert(config.translations.error_upload_size);
                return false;
            }
            
            // Verifica tipo MIME
            if (allowedTypes.indexOf(file.type) === -1) {
                $('#form-upload input[name=\'file\']').val('');
                alert('Tipo de arquivo não permitido. Apenas imagens são aceitas (JPEG, PNG, GIF, WebP).');
                return false;
            }
            
            // Verifica extensão
            if (allowedExtensions.indexOf(fileExtension) === -1) {
                $('#form-upload input[name=\'file\']').val('');
                alert('Extensão de arquivo não permitida. Use: .jpg, .jpeg, .png, .gif, .webp');
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Upload de arquivo único
     */
    function uploadSingleFile() {
        debugLog('Iniciando upload de arquivo único');
        
        var formData = new FormData($('#form-upload')[0]);
        var url = buildUploadUrl();
        
        performAjaxUpload(url, formData, function(success) {
            if (success) {
                setTimeout(function() {
                    $('#button-refresh').trigger('click');
                }, 1000);
            }
        });
    }
    
    /**
     * Upload de múltiplos arquivos (um por vez)
     */
    function uploadMultipleFiles(fileList) {
        debugLog('Iniciando upload de múltiplos arquivos:', fileList.length);
        
        var currentIndex = 0;
        var totalFiles = fileList.length;
        var successCount = 0;
        var errorCount = 0;

        function uploadNext() {
            if (currentIndex >= totalFiles) {
                handleMultipleUploadComplete(successCount, errorCount);
                return;
            }

            var file = fileList[currentIndex];
            debugLog('Enviando arquivo ' + (currentIndex + 1) + ' de ' + totalFiles + ':', file.name);

            var formData = new FormData();
            formData.append('file', file);
            var url = buildUploadUrl();

            $('#button-upload').html('Enviando ' + (currentIndex + 1) + '/' + totalFiles + '...');
            $('#button-upload').prop('disabled', true);

            performAjaxUpload(url, formData, function(success) {
                if (success) {
                    successCount++;
                } else {
                    errorCount++;
                }
                
                currentIndex++;
                
                if (currentIndex >= totalFiles) {
                    $('#button-upload').button('reset');
                    $('#button-upload').prop('disabled', false);
                }
                
                uploadNext();
            });
        }

        uploadNext();
    }
    
    /**
     * Constrói URL para upload
     */
    function buildUploadUrl() {
        var url = 'index.php?route=common/filemanager.upload&user_token=' + config.userToken;
        var directory = $('#input-directory').val();

        if (directory) {
            url += '&directory=' + encodeURIComponent(directory);
        }

        return url;
    }
    
    /**
     * Executa requisição AJAX de upload
     */
    function performAjaxUpload(url, formData, callback) {
        $.ajax({
            url: url,
            type: 'post',
            dataType: 'json',
            data: formData,
            cache: false,
            contentType: false,
            processData: false,
            xhr: function() {
                return createUploadXHR();
            },
            beforeSend: function () {
                $('#button-upload').button('loading');
                $('#button-upload').prop('disabled', true);
            },
            complete: function () {
                $('#button-upload').button('reset');
                $('#button-upload').prop('disabled', false);
            },
            success: function (json) {
                handleUploadResponse(json, callback);
            },
            error: function (xhr, ajaxOptions, thrownError) {
                debugLog('Erro na requisição AJAX:', {
                    status: xhr.status,
                    statusText: xhr.statusText,
                    responseText: xhr.responseText.substring(0, 500),
                    thrownError: thrownError
                });
                alert('Erro no upload: ' + thrownError);
                callback(false);
            }
        });
    }
    
    /**
     * Cria objeto XMLHttpRequest para upload com progresso
     */
    function createUploadXHR() {
        var xhr = new window.XMLHttpRequest();
        xhr.upload.addEventListener("progress", function(evt) {
            if (evt.lengthComputable) {
                var percentComplete = parseInt((evt.loaded / evt.total) * 100);
                $('#button-upload').html('Enviando... ' + percentComplete + '%');
            }
        }, false);
        return xhr;
    }
    
    /**
     * Manipula resposta do upload
     */
    function handleUploadResponse(json, callback) {
        debugLog('Resposta do servidor recebida:', json);
        
        try {
            if (json && typeof json === 'object') {
                if (json.error && json.error.trim() !== '') {
                    debugLog('Erro retornado pelo servidor:', json.error);
                    alert(json.error);
                    callback(false);
                    return;
                }

                if (json.success && json.success.trim() !== '') {
                    debugLog('Upload bem-sucedido:', json.success);
                    callback(true);
                    return;
                }
                
                // Se não há mensagem explícita, assume sucesso
                callback(true);
            } else {
                debugLog('Resposta JSON inválida:', json);
                callback(false);
            }
        } catch (e) {
            debugLog('Exceção ao processar resposta:', e);
            callback(false);
        }
    }
    
    /**
     * Finaliza upload múltiplo
     */
    function handleMultipleUploadComplete(successCount, errorCount) {
        debugLog('Upload múltiplo concluído. Sucessos: ' + successCount + ', Erros: ' + errorCount);
        
        if (successCount > 0) {
            alert('Upload concluído! ' + successCount + ' arquivo(s) enviado(s) com sucesso' + 
                  (errorCount > 0 ? ', ' + errorCount + ' com erro.' : '.'));
            
            setTimeout(function() {
                $('#button-refresh').trigger('click');
            }, 1000);
        }
    }
    
    /**
     * Cria nova pasta
     */
    function createFolder() {
        var folderName = $('#input-folder').val();
        
        if (!folderName || folderName.trim() === '') {
            alert('Digite um nome para a pasta.');
            return;
        }

        var url = 'index.php?route=common/filemanager.folder&user_token=' + config.userToken;
        var directory = $('#input-directory').val();

        if (directory) {
            url += '&directory=' + encodeURIComponent(directory);
        }

        $.ajax({
            url: url,
            type: 'post',
            dataType: 'json',
            data: 'folder=' + encodeURIComponent(folderName),
            beforeSend: function () {
                $('#button-create').button('loading');
                $('#button-create').prop('disabled', true);
            },
            complete: function () {
                $('#button-create').button('reset');
                $('#button-create').prop('disabled', false);
            },
            success: function (json) {
                handleFolderResponse(json);
            },
            error: function (xhr, ajaxOptions, thrownError) {
                debugLog('Erro ao criar pasta:', thrownError);
                alert('Erro ao criar pasta: ' + thrownError);
            }
        });
    }
    
    /**
     * Manipula resposta da criação de pasta
     */
    function handleFolderResponse(json) {
        try {
            if (json && typeof json === 'object') {
                if (json.error && json.error.trim() !== '') {
                    alert(json.error);
                    return;
                }

                if (json.success && json.success.trim() !== '') {
                    alert(json.success);
                    $('#input-folder').val('');
                    $('#button-refresh').trigger('click');
                }
            }
        } catch (e) {
            debugLog('Erro ao processar resposta da criação de pasta:', e);
        }
    }
    
    /**
     * Exclui arquivos selecionados
     */
    function deleteSelectedFiles() {
        var selectedFiles = $('input[name^=\'path\']:checked');
        
        if (selectedFiles.length === 0) {
            alert('Selecione pelo menos um arquivo para excluir.');
            return;
        }

        if (confirm(config.translations.text_confirm)) {
            $.ajax({
                url: 'index.php?route=common/filemanager.delete&user_token=' + config.userToken,
                type: 'post',
                dataType: 'json',
                data: selectedFiles.serialize(),
                beforeSend: function () {
                    $('#button-delete').button('loading');
                    $('#button-delete').prop('disabled', true);
                },
                complete: function () {
                    $('#button-delete').button('reset');
                    $('#button-delete').prop('disabled', false);
                },
                success: function (json) {
                    handleDeleteResponse(json);
                },
                error: function (xhr, ajaxOptions, thrownError) {
                    debugLog('Erro ao excluir:', thrownError);
                    alert('Erro ao excluir: ' + thrownError);
                }
            });
        }
    }
    
    /**
     * Manipula resposta da exclusão
     */
    function handleDeleteResponse(json) {
        try {
            if (json && typeof json === 'object') {
                if (json.error && json.error.trim() !== '') {
                    alert(json.error);
                    return;
                }

                if (json.success && json.success.trim() !== '') {
                    alert(json.success);
                    $('#button-refresh').trigger('click');
                }
            }
        } catch (e) {
            debugLog('Erro ao processar resposta da exclusão:', e);
        }
    }
    
    /**
     * Seleciona uma imagem
     */
    function selectImage($thumbnail) {
        if (config.thumb) {
            $(config.thumb).attr('src', $thumbnail.find('img').attr('src'));
            $(config.target).val('catalog/' + $thumbnail.parent().parent().find('input').val());
        }

        if (config.ckeditor) {
            CKEDITOR.instances[config.ckeditor].insertHtml('<img src="' + $thumbnail.attr('href') + '" alt="" title=""/>');
        }

        $('#modal-image').modal('hide');
    }
    
    // API pública
    return {
        init: init
    };
    
})();

// ===================
// 8. IMAGE MANAGER
// ===================

/**
 * Enhanced image manager with error handling
 */
$(document).on('click', '[data-rms-toggle="image"]', function() {
    const element = this;
    const $element = $(element);
    const target = $element.attr('data-rms-target');
    const thumb = $element.attr('data-rms-thumb');

    if (!target) {
        console.error('No target specified for image manager');
        return;
    }

    $('#modal-image').remove();

    const userToken = getURLVar('user_token');
    const url = `index.php?route=common/filemanager&user_token=${userToken}&target=${encodeURIComponent(target)}&thumb=${encodeURIComponent(thumb || '')}`;

    $.ajax({
        url: url,
        dataType: 'html',
        timeout: 15000,
        beforeSend: function() {
            $element.button('loading');
        },
        complete: function() {
            $element.button('reset');
        },
        success: function(html) {
            if (html) {
                $('body').append(html);
                
                const modalElement = document.querySelector('#modal-image');
                if (modalElement) {
                    const modal = new bootstrap.Modal(modalElement);
                    modal.show();
                }
            }
        },
        error: function(xhr, status, error) {
            console.error('Failed to load image manager:', error);
            alert('Failed to load image manager. Please try again.');
        }
    });
});

// ===================
// 9. CHAIN AJAX HANDLER
// ===================

/**
 * Chain class for sequential AJAX calls
 */
class Chain {
    constructor() {
        this.start = false;
        this.queue = [];
        this.currentRequest = null;
    }

    /**
     * Add AJAX call to the chain
     * @param {Function} call - Function that returns jQuery AJAX promise
     */
    attach(call) {
        if (typeof call !== 'function') {
            console.error('Chain.attach expects a function');
            return;
        }

        this.queue.push(call);

        if (!this.start) {
            this.execute();
        }
    }

    /**
     * Execute next call in the chain
     */
    execute() {
        if (this.queue.length > 0) {
            this.start = true;
            const call = this.queue.shift();
            
            try {
                this.currentRequest = call();
                
                if (this.currentRequest && typeof this.currentRequest.done === 'function') {
                    this.currentRequest
                        .done(() => {
                            this.execute();
                        })
                        .fail((xhr, status, error) => {
                            console.error('Chain AJAX call failed:', error);
                            this.execute(); // Continue with next call even if one fails
                        });
                } else {
                    console.warn('Chain call did not return a promise');
                    this.execute();
                }
            } catch (error) {
                console.error('Error executing chain call:', error);
                this.execute();
            }
        } else {
            this.start = false;
            this.currentRequest = null;
        }
    }

    /**
     * Clear all pending calls
     */
    clear() {
        this.queue = [];
        if (this.currentRequest && typeof this.currentRequest.abort === 'function') {
            this.currentRequest.abort();
        }
        this.start = false;
        this.currentRequest = null;
    }
}

// Global chain instance
const chain = new Chain();

// ===================
// 10. AUTOCOMPLETE PLUGIN
// ===================

/**
 * Enhanced autocomplete plugin with debouncing and error handling
 */
(function($) {
    const DEBOUNCE_DELAY = 300;
    const MIN_QUERY_LENGTH = 2;

    $.fn.autocomplete = function(options) {
        const defaults = {
            minLength: MIN_QUERY_LENGTH,
            delay: DEBOUNCE_DELAY,
            source: null,
            select: null
        };

        return this.each(function() {
            const element = this;
            const $element = $(element);
            const settings = $.extend({}, defaults, options);
            const targetId = $element.attr('data-rms-target');
            
            if (!targetId) {
                console.error('Autocomplete: No target specified');
                return;
            }

            const $dropdown = $('#' + targetId);
            
            if (!$dropdown.length) {
                console.error('Autocomplete: Target dropdown not found');
                return;
            }

            let timer = null;
            let items = {};
            let currentRequest = null;

            // Focus in - show dropdown if has content
            $element.on('focusin', function() {
                if ($dropdown.find('li').length > 0) {
                    $dropdown.addClass('show');
                }
            });

            // Focus out - hide dropdown unless clicking on item
            $element.on('focusout', function(e) {
                setTimeout(function() {
                    if (!$dropdown.is(':hover')) {
                        $dropdown.removeClass('show');
                    }
                }, 100);
            });

            // Input - trigger search with debouncing
            $element.on('input', function() {
                const query = $(this).val();
                
                if (query.length >= settings.minLength) {
                    clearTimeout(timer);
                    timer = setTimeout(function() {
                        search(query);
                    }, settings.delay);
                } else {
                    clearTimeout(timer);
                    $dropdown.removeClass('show');
                }
            });

            // Click on dropdown item
            $dropdown.on('click', 'a', function(e) {
                e.preventDefault();
                
                const value = $(this).attr('href');
                
                if (items[value]) {
                    if (typeof settings.select === 'function') {
                        settings.select(items[value]);
                    }
                    $dropdown.removeClass('show');
                }
            });

            /**
             * Perform search
             * @param {string} query - Search query
             */
            function search(query) {
                if (!settings.source || typeof settings.source !== 'function') {
                    console.error('Autocomplete: No source function provided');
                    return;
                }

                // Cancel previous request
                if (currentRequest) {
                    currentRequest.abort();
                }

                // Clear current results
                $dropdown.find('li').remove();
                
                // Show loading indicator
                $dropdown.prepend(`
                    <li id="autocomplete-loading">
                        <span class="dropdown-item text-center disabled">
                            <i class="fa-solid fa-circle-notch fa-spin"></i>
                        </span>
                    </li>
                `);
                $dropdown.addClass('show');

                // Make request
                try {
                    currentRequest = settings.source(query, function(results) {
                        displayResults(results);
                    });
                } catch (error) {
                    console.error('Autocomplete search error:', error);
                    $dropdown.removeClass('show');
                }
            }

            /**
             * Display search results
             * @param {Array} results - Search results
             */
            function displayResults(results) {
                $('#autocomplete-loading').remove();
                
                if (!Array.isArray(results) || results.length === 0) {
                    $dropdown.removeClass('show');
                    return;
                }

                let html = '';
                const categories = {};
                items = {}; // Reset items

                // Process results
                results.forEach(function(item) {
                    if (!item.value || !item.label) {
                        return; // Skip invalid items
                    }

                    items[item.value] = item;

                    if (!item.category) {
                        // Ungrouped items
                        html += `<li><a href="${item.value}" class="dropdown-item">${item.label}</a></li>`;
                    } else {
                        // Grouped items
                        const category = item.category;
                        if (!categories[category]) {
                            categories[category] = [];
                        }
                        categories[category].push(item);
                    }
                });

                // Add categorized items
                Object.keys(categories).forEach(function(categoryName) {
                    html += `<li><h6 class="dropdown-header">${categoryName}</h6></li>`;
                    
                    categories[categoryName].forEach(function(item) {
                        html += `<li><a href="${item.value}" class="dropdown-item">${item.label}</a></li>`;
                    });
                });

                if (html) {
                    $dropdown.html(html);
                    $dropdown.addClass('show');
                } else {
                    $dropdown.removeClass('show');
                }
            }

            // Cleanup on element removal
            $element.on('remove', function() {
                clearTimeout(timer);
                if (currentRequest) {
                    currentRequest.abort();
                }
            });
        });
    };
})(jQuery);

// ===================
// 11. BUTTON STATE PLUGIN
// ===================

/**
 * Enhanced button state management
 */
$(document).ready(function() {
    (function($) {
        $.fn.button = function(state) {
            return this.each(function() {
                const element = this;
                const $element = $(element);

                if (state === 'loading') {
                    // Store original state
                    $element.data('original-html', $element.html());
                    $element.data('original-disabled', $element.prop('disabled'));
                    $element.data('original-width', $element.outerWidth());

                    // Set loading state
                    $element
                        .prop('disabled', true)
                        .width($element.data('original-width'))
                        .html('<i class="fa-solid fa-circle-notch fa-spin"></i>');

                } else if (state === 'reset') {
                    // Restore original state
                    const originalHtml = $element.data('original-html');
                    const originalDisabled = $element.data('original-disabled');

                    if (originalHtml !== undefined) {
                        $element.html(originalHtml);
                    }
                    
                    $element
                        .prop('disabled', originalDisabled || false)
                        .width('');

                    // Clean up data
                    $element.removeData('original-html original-disabled original-width');
                }
            });
        };
    })(jQuery);
});

// ===================
// MODAL FIX
// ===================
/**
 * Modal accessibility fix for Reamur CMS
 * 
 * This script overrides Bootstrap's modal implementation to prevent aria-hidden
 * from being applied to modals that may contain focused elements.
 * Instead, it uses the 'inert' attribute as recommended by accessibility guidelines.
 */

document.addEventListener('DOMContentLoaded', function() {
  // Override Bootstrap's Modal._hideModal method to use inert instead of aria-hidden
  if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
    const originalProto = bootstrap.Modal.prototype;
    const originalHideModal = originalProto._hideModal;
    const originalShowElement = originalProto._showElement;
    
    // Override the _showElement method to ensure proper accessibility attributes
    originalProto._showElement = function(relatedTarget) {
      // Call the original method first
      originalShowElement.call(this, relatedTarget);
      
      // Ensure the modal has tabindex for proper focus management
      if (!this._element.hasAttribute('tabindex')) {
        this._element.setAttribute('tabindex', '-1');
      }
    };
    
    // Override the _hideModal method to use inert instead of aria-hidden
    originalProto._hideModal = function() {
      this._element.style.display = 'none';
      
      // Use inert attribute instead of aria-hidden
      // This prevents the accessibility issue with focused elements
      this._element.removeAttribute('aria-hidden');
      if ('inert' in this._element) {
        this._element.inert = true;
      }
      
      this._element.removeAttribute('aria-modal');
      this._element.removeAttribute('role');
      this._isTransitioning = false;
      
      this._backdrop.hide(() => {
        document.body.classList.remove('modal-open');
        this._resetAdjustments();
        this._scrollBar.reset();
        
        // Use native dispatchEvent instead of bootstrap.EventHandler.trigger
        const hiddenEvent = new Event('hidden.bs.modal', { bubbles: true, cancelable: true });
        this._element.dispatchEvent(hiddenEvent);
        
        // Remove inert when fully hidden
        if ('inert' in this._element) {
          this._element.inert = false;
        }
      });
    };
  }
});

// ===================
// UTILITY FUNCTIONS
// ===================

/**
 * Debounce function to limit function calls
 * @param {Function} func - Function to debounce
 * @param {number} wait - Wait time in milliseconds
 * @param {boolean} immediate - Execute immediately
 * @returns {Function} Debounced function
 */
function debounce(func, wait, immediate) {
    let timeout;
    return function executedFunction() {
        const context = this;
        const args = arguments;
        
        const later = function() {
            timeout = null;
            if (!immediate) func.apply(context, args);
        };
        
        const callNow = immediate && !timeout;
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
        
        if (callNow) func.apply(context, args);
    };
}

/**
 * Throttle function to limit function execution frequency
 * @param {Function} func - Function to throttle
 * @param {number} limit - Time limit in milliseconds
 * @returns {Function} Throttled function
 */
function throttle(func, limit) {
    let inThrottle;
    return function() {
        const args = arguments;
        const context = this;
        if (!inThrottle) {
            func.apply(context, args);
            inThrottle = true;
            setTimeout(() => inThrottle = false, limit);
        }
    };
}

/**
 * Escape HTML to prevent XSS
 * @param {string} text - Text to escape
 * @returns {string} Escaped text
 */
function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, function(m) { return map[m]; });
}

/**
 * Format file size in human readable format
 * @param {number} bytes - File size in bytes
 * @returns {string} Formatted file size
 */
function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

/**
 * Generate unique ID
 * @returns {string} Unique identifier
 */
function generateId() {
    return 'id_' + Math.random().toString(36).substr(2, 9) + '_' + Date.now();
}

// ===================
// GLOBAL ERROR HANDLING
// ===================

/**
 * Global error handler for uncaught errors
 */
window.addEventListener('error', function(e) {
    console.error('Global error caught:', e.error);
    
    // Don't show user errors for script loading failures
    if (e.filename && e.filename.includes('.js')) {
        return;
    }
    
    // Show user-friendly error for AJAX failures
    if (e.error && e.error.message && e.error.message.includes('AJAX')) {
        showAlert('danger', 'A network error occurred. Please check your connection and try again.');
    }
});

/**
 * Global handler for unhandled promise rejections
 */
window.addEventListener('unhandledrejection', function(e) {
    console.error('Unhandled promise rejection:', e.reason);
    
    // Prevent the default browser behavior
    e.preventDefault();
    
    // Show user-friendly error
    showAlert('warning', 'An operation failed to complete. Please try again.');
});

// ===================
// INITIALIZATION
// ===================

/**
 * Initialize common functionality when DOM is ready
 */
$(document).ready(function() {
    console.log('Common.js initialized successfully');
    
    // Add loading class to body for CSS transitions
    $('body').addClass('js-loaded');
    
    // Initialize any existing tooltips
    $('[data-bs-toggle="tooltip"]').each(function() {
        try {
            new bootstrap.Tooltip(this);
        } catch (error) {
            console.warn('Failed to initialize tooltip:', error);
        }
    });
    
    // Add CSRF token to all AJAX requests if available
    const csrfToken = $('meta[name="csrf-token"]').attr('content');
    if (csrfToken) {
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': csrfToken
            }
        });
    }
    
    // Set default AJAX settings
    $.ajaxSetup({
        timeout: 30000,
        cache: false,
        error: function(xhr, status, error) {
            if (status !== 'abort') {
                console.error('AJAX Error:', status, error);
            }
        }
    });
});

// ===================
// PERFORMANCE MONITORING
// ===================

/**
 * Simple performance monitoring
 */
if (window.performance && window.performance.mark) {
    $(document).ready(function() {
        window.performance.mark('common-js-loaded');
    });
    
    $(window).on('load', function() {
        window.performance.mark('page-fully-loaded');
        
        // Log performance metrics
        setTimeout(function() {
            const navigation = window.performance.getEntriesByType('navigation')[0];
            if (navigation) {
                console.log('Page Load Performance:', {
                    'DOM Content Loaded': navigation.domContentLoadedEventEnd - navigation.domContentLoadedEventStart + 'ms',
                    'Full Load Time': navigation.loadEventEnd - navigation.loadEventStart + 'ms',
                    'DNS Lookup': navigation.domainLookupEnd - navigation.domainLookupStart + 'ms'
                });
            }
        }, 0);
    });
}

// ===================
// ACCESSIBILITY ENHANCEMENTS
// ===================

/**
 * Add keyboard navigation support
 */
$(document).ready(function() {
    // Add keyboard support for custom dropdowns
    $(document).on('keydown', '[data-bs-toggle="dropdown"]', function(e) {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            $(this).click();
        }
    });
    
    // Add skip to content functionality
    if (!$('#skip-to-content').length) {
        $('body').prepend(`
            <a href="#main-content" id="skip-to-content" class="sr-only sr-only-focusable">
                Skip to main content
            </a>
        `);
    }
    
    // Announce dynamic content changes to screen readers
    let announcer = $('#live-announcer');
    if (!announcer.length) {
        announcer = $('<div id="live-announcer" aria-live="polite" aria-atomic="true" class="sr-only"></div>');
        $('body').append(announcer);
    }
});

/**
 * Announce message to screen readers
 * @param {string} message - Message to announce
 */
function announceToScreenReader(message) {
    const announcer = $('#live-announcer');
    if (announcer.length) {
        announcer.text(message);
        setTimeout(() => announcer.text(''), 1000);
    }
}

// ===================
// EXPORT FOR MODULE SYSTEMS
// ===================

// Support for CommonJS
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        getURLVar,
        isIE,
        showAlert,
        debounce,
        throttle,
        escapeHtml,
        formatFileSize,
        generateId,
        announceToScreenReader,
        Chain
    };
}

// Support for AMD
if (typeof define === 'function' && define.amd) {
    define(function() {
        return {
            getURLVar,
            isIE,
            showAlert,
            debounce,
            throttle,
            escapeHtml,
            formatFileSize,
            generateId,
            announceToScreenReader,
            Chain
        };
    });
}

// ===================
// END OF FILE
// ===================
