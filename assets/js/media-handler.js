/**
 * Media Handler - obsługa przesyłania nowych obrazów (bez dostępu do biblioteki mediów)
 */
(function($) {
    'use strict';
    
    // Funkcja inicjalizująca obsługę przesyłania obrazów
    function initImageUpload() {
        // Elementy DOM
        const $fileInputs = $('.herbal-mix-file-input');
        const $selectButtons = $('.herbal-mix-select-image-btn');
        const $removeButtons = $('.herbal-mix-remove-image-btn');
        const $imagePreviews = $('.image-preview');
        
        // Domyślne ustawienia
        const defaultImage = 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 24 24" fill="none" stroke="%23ccc" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>';
        
        // Sprawdź czy mamy włączoną obsługę MediaHandler
        if (!window.herbalMediaData) {
            console.error('Herbal Media Handler: Missing configuration data');
            return;
        }
        
        console.log('Herbal Media Handler initialized');
        
        // Obsługa kliknięcia na przycisk "Upload New Image"
        $selectButtons.on('click', function(e) {
            e.preventDefault();
            
            const targetId = $(this).data('target');
            const $fileInput = $(`#${targetId}_file`);
            
            console.log('Upload button clicked for:', targetId);
            
            // Wywołaj input file - tylko przesyłanie nowych plików
            $fileInput.trigger('click');
        });
        
        // Obsługa kliknięcia na podgląd obrazu (jeśli nie ma obrazu)
        $imagePreviews.on('click', function() {
            if (!$(this).hasClass('has-image')) {
                const targetId = $(this).attr('id').replace('_preview', '');
                $(`#${targetId}_select_btn`).trigger('click');
            }
        });
        
        // Obsługa wyboru pliku w input file
        $fileInputs.on('change', function(e) {
            if (!this.files || !this.files[0]) {
                return;
            }
            
            const file = this.files[0];
            const targetId = $(this).data('target');
            
            console.log('File selected:', file.name, 'for target:', targetId);
            
            const $preview = $(`#${targetId}_preview`);
            const $progressContainer = $(`#${targetId}_progress_container`);
            const $progressBar = $(`#${targetId}_progress_bar`);
            const $progressText = $(`#${targetId}_progress_text`);
            const $errorMsg = $(`#${targetId}_error`);
            const $selectBtn = $(`#${targetId}_select_btn`);
            const $removeBtn = $(`#${targetId}_remove_btn`);
            const $hiddenInput = $(`#${targetId}`);
            
            // Walidacja typu pliku
            if (herbalMediaData.allowedTypes.indexOf(file.type) === -1) {
                $errorMsg.text(herbalMediaData.messages.wrongType).show();
                this.value = ''; // Wyczyść input
                return;
            }
            
            // Walidacja rozmiaru pliku
            if (file.size > herbalMediaData.maxFileSize) {
                $errorMsg.text(`${herbalMediaData.messages.tooLarge} ${formatFileSize(herbalMediaData.maxFileSize)}`).show();
                this.value = ''; // Wyczyść input
                return;
            }
            
            // Pokaż podgląd przed wysłaniem
            const reader = new FileReader();
            reader.onload = function(e) {
                $preview.css('background-image', `url(${e.target.result})`).addClass('has-image');
            };
            reader.readAsDataURL(file);
            
            // Ukryj błędy
            $errorMsg.hide();
            
            // Pokaż pasek postępu
            if ($progressContainer.length) {
                $progressContainer.show();
                $progressBar.css('width', '0%');
                $progressText.text('0%');
            }
            
            // Zablokuj przyciski podczas wysyłania
            $selectBtn.prop('disabled', true).text('Uploading...');
            $removeBtn.prop('disabled', true);
            
            // Przygotuj dane formularza
            const formData = new FormData();
            
            // Sprawdź typ pola (avatar czy normalny obraz)
            if (targetId.includes('avatar')) {
                formData.append('action', 'upload_avatar');
                formData.append('nonce', herbalMediaData.uploadAvatarNonce);
                formData.append('avatar_image', file);
            } else {
                formData.append('action', 'upload_mix_image');
                formData.append('nonce', herbalMediaData.uploadImageNonce);
                formData.append('mix_image', file);
            }
            
            console.log('Starting upload for:', file.name);
            
            // Wyślij plik
            $.ajax({
                url: herbalMediaData.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                xhr: function() {
                    const xhr = new window.XMLHttpRequest();
                    
                    // Obsługa postępu
                    xhr.upload.addEventListener('progress', function(evt) {
                        if (evt.lengthComputable && $progressContainer.length) {
                            const percentComplete = Math.round((evt.loaded / evt.total) * 100);
                            $progressBar.css('width', percentComplete + '%');
                            $progressText.text(percentComplete + '%');
                        }
                    }, false);
                    
                    return xhr;
                },
                success: function(response) {
                    console.log('Upload response:', response);
                    
                    // Przywróć przyciski
                    $selectBtn.prop('disabled', false).text('Upload New Image');
                    $removeBtn.prop('disabled', false);
                    
                    // Ukryj pasek postępu po chwili
                    if ($progressContainer.length) {
                        setTimeout(function() {
                            $progressContainer.hide();
                        }, 1000);
                    }
                    
                    if (response.success) {
                        // Ustaw URL obrazu w ukrytym polu
                        $hiddenInput.val(response.data.url);
                        
                        // Aktualizuj podgląd z końcowym obrazem
                        $preview.css('background-image', `url(${response.data.url})`).addClass('has-image');
                        
                        // Pokaż przycisk usuwania
                        $removeBtn.show();
                        
                        // Pokaż komunikat sukcesu
                        $errorMsg.removeClass('error-message').addClass('success-message')
                            .text(herbalMediaData.messages.uploadSuccess).show()
                            .delay(3000).fadeOut();
                        
                        // Wywołaj event change na ukrytym polu dla walidacji formularza
                        $hiddenInput.trigger('change');
                        
                        console.log('Upload successful, URL:', response.data.url);
                        
                    } else {
                        console.error('Upload failed:', response.data);
                        
                        // Pokaż błąd
                        $errorMsg.removeClass('success-message').addClass('error-message')
                            .text('Upload error: ' + (response.data || herbalMediaData.messages.uploadError)).show();
                        
                        // Przywróć domyślny wygląd
                        $preview.css('background-image', `url(${defaultImage})`).removeClass('has-image');
                        $hiddenInput.val('');
                        $removeBtn.hide();
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Upload AJAX error:', status, error, xhr.responseText);
                    
                    // Przywróć przyciski
                    $selectBtn.prop('disabled', false).text('Upload New Image');
                    $removeBtn.prop('disabled', false);
                    
                    // Ukryj pasek postępu
                    if ($progressContainer.length) {
                        $progressContainer.hide();
                    }
                    
                    // Pokaż błąd
                    let errorMessage = herbalMediaData.messages.connectionError;
                    
                    // Spróbuj wydobyć bardziej szczegółowy błąd
                    if (xhr.responseJSON && xhr.responseJSON.data) {
                        errorMessage = 'Server error: ' + xhr.responseJSON.data;
                    } else if (xhr.responseText) {
                        try {
                            const responseData = JSON.parse(xhr.responseText);
                            if (responseData.data) {
                                errorMessage = 'Error: ' + responseData.data;
                            }
                        } catch (e) {
                            // Jeśli nie można sparsować JSON, pokaż domyślny błąd
                        }
                    }
                    
                    $errorMsg.removeClass('success-message').addClass('error-message')
                        .text(errorMessage).show();
                    
                    // Przywróć domyślny wygląd
                    $preview.css('background-image', `url(${defaultImage})`).removeClass('has-image');
                    $hiddenInput.val('');
                    $removeBtn.hide();
                }
            });
        });
        
        // Obsługa przycisku usuwania obrazu
        $removeButtons.on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const targetId = $(this).data('target');
            
            console.log('Remove button clicked for:', targetId);
            
            // Usuń URL obrazu
            $(`#${targetId}`).val('');
            
            // Przywróć domyślny wygląd
            $(`#${targetId}_preview`).css('background-image', `url(${defaultImage})`).removeClass('has-image');
            
            // Ukryj przycisk usuwania
            $(this).hide();
            
            // Wyczyść pole pliku
            $(`#${targetId}_file`).val('');
            
            // Ukryj błędy
            $(`#${targetId}_error`).hide();
            
            // Wywołaj event change dla walidacji formularza
            $(`#${targetId}`).trigger('change');
        });
        
        // Drag and drop support (opcjonalne)
        $imagePreviews.on('dragover', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).addClass('drag-over');
        });
        
        $imagePreviews.on('dragleave', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).removeClass('drag-over');
        });
        
        $imagePreviews.on('drop', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).removeClass('drag-over');
            
            const files = e.originalEvent.dataTransfer.files;
            if (files.length > 0) {
                const targetId = $(this).attr('id').replace('_preview', '');
                const $fileInput = $(`#${targetId}_file`);
                
                // Ustaw plik w input i wywołaj event change
                $fileInput[0].files = files;
                $fileInput.trigger('change');
            }
        });
    }
    
    // Funkcja formatująca rozmiar pliku
    function formatFileSize(bytes) {
        if (bytes >= 1073741824) {
            return (bytes / 1073741824).toFixed(2) + ' GB';
        } else if (bytes >= 1048576) {
            return (bytes / 1048576).toFixed(2) + ' MB';
        } else if (bytes >= 1024) {
            return (bytes / 1024).toFixed(2) + ' KB';
        } else {
            return bytes + ' B';
        }
    }
    
    // Inicjalizacja po załadowaniu dokumentu
    $(document).ready(function() {
        console.log('Document ready, initializing image upload');
        initImageUpload();
    });
    
    // Eksportuj funkcję dla użycia zewnętrznego
    window.herbalMediaHandler = {
        init: initImageUpload,
        formatFileSize: formatFileSize
    };
    
})(jQuery);