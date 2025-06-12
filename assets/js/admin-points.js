/**
 * JavaScript dla panelu administracyjnego zarządzania punktami
 * Plik: assets/js/admin-points.js
 */

jQuery(document).ready(function($) {
    
    // Obsługa zmiany użytkownika - pokaż aktualne punkty
    $('#user-select').on('change', function() {
        var userId = $(this).val();
        var currentPointsField = $('#current-points');
        
        if (!userId) {
            currentPointsField.val('').attr('placeholder', 'Wybierz użytkownika');
            return;
        }
        
        currentPointsField.val('Ładowanie...').prop('disabled', true);
        
        $.ajax({
            url: herbal_admin_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'admin_get_user_points',
                user_id: userId,
                nonce: herbal_admin_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    currentPointsField.val(parseFloat(response.data.points).toFixed(2));
                } else {
                    currentPointsField.val('Błąd');
                    console.error('Error loading user points:', response.data);
                }
            },
            error: function(xhr, status, error) {
                currentPointsField.val('Błąd połączenia');
                console.error('AJAX error:', error);
            },
            complete: function() {
                currentPointsField.prop('disabled', true); // Keep it readonly
            }
        });
    });
    
    // Obsługa formularza dostosowywania punktów
    $('#adjust-points-form').on('submit', function(e) {
        e.preventDefault();
        
        var form = $(this);
        var submitButton = form.find('input[type="submit"]');
        var originalText = submitButton.val();
        
        // Walidacja
        var userId = $('#user-select').val();
        var adjustmentType = $('#adjustment-type').val();
        var pointsAmount = parseFloat($('#points-amount').val());
        
        if (!userId) {
            alert('Wybierz użytkownika');
            return;
        }
        
        if (isNaN(pointsAmount) || pointsAmount < 0) {
            alert('Wprowadź prawidłową ilość punktów (większą lub równą 0)');
            return;
        }
        
        // Potwierdzenie dla operacji odejmowania lub ustawiania
        if (adjustmentType === 'subtract' || adjustmentType === 'set') {
            var currentPoints = parseFloat($('#current-points').val()) || 0;
            var confirmMessage = '';
            
            if (adjustmentType === 'subtract') {
                if (pointsAmount > currentPoints) {
                    confirmMessage = 'Użytkownik ma tylko ' + currentPoints.toFixed(2) + 
                                   ' punktów. Czy na pewno chcesz odjąć ' + pointsAmount.toFixed(2) + ' punktów?';
                } else {
                    confirmMessage = 'Czy na pewno chcesz odjąć ' + pointsAmount.toFixed(2) + ' punktów?';
                }
            } else if (adjustmentType === 'set') {
                confirmMessage = 'Czy na pewno chcesz ustawić punkty na ' + pointsAmount.toFixed(2) + 
                               '? (aktualne: ' + currentPoints.toFixed(2) + ')';
            }
            
            if (!confirm(confirmMessage)) {
                return;
            }
        }
        
        // Wyślij żądanie
        submitButton.val('Przetwarzanie...').prop('disabled', true);
        
        $.ajax({
            url: herbal_admin_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'admin_adjust_user_points',
                user_id: userId,
                adjustment_type: adjustmentType,
                points_amount: pointsAmount,
                reason: $('#adjustment-reason').val(),
                nonce: herbal_admin_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Pokaż komunikat sukcesu
                    showNotice('success', response.data.message);
                    
                    // Zaktualizuj pole aktualnych punktów
                    $('#current-points').val(parseFloat(response.data.new_points).toFixed(2));
                    
                    // Wyczyść formularz (oprócz użytkownika)
                    $('#points-amount').val('');
                    $('#adjustment-reason').val('');
                    $('#adjustment-type').val('add');
                    
                } else {
                    showNotice('error', 'Błąd: ' + response.data);
                }
            },
            error: function(xhr, status, error) {
                showNotice('error', 'Błąd połączenia: ' + error);
            },
            complete: function() {
                submitButton.val(originalText).prop('disabled', false);
            }
        });
    });
    
    // Walidacja na żywo
    $('#points-amount').on('input', function() {
        var value = parseFloat($(this).val());
        var adjustmentType = $('#adjustment-type').val();
        var currentPoints = parseFloat($('#current-points').val()) || 0;
        
        if (isNaN(value) || value < 0) {
            $(this).css('border-color', '#dc3545');
            return;
        }
        
        if (adjustmentType === 'subtract' && value > currentPoints) {
            $(this).css('border-color', '#ffc107');
        } else {
            $(this).css('border-color', '#28a745');
        }
    });
    
    // Reset koloru przy zmianie typu operacji
    $('#adjustment-type').on('change', function() {
        $('#points-amount').trigger('input');
    });
    
    // Funkcja do wyświetlania powiadomień
    function showNotice(type, message) {
        var noticeClass = type === 'success' ? 'notice-success' : 'notice-error';
        var notice = $('<div class="notice ' + noticeClass + ' is-dismissible"><p>' + message + '</p></div>');
        
        // Usuń poprzednie powiadomienia
        $('.wrap .notice').remove();
        
        // Dodaj nowe powiadomienie
        $('.wrap h1').after(notice);
        
        // Dodaj funkcjonalność zamykania
        notice.find('.notice-dismiss').on('click', function() {
            notice.remove();
        });
        
        // Auto-ukryj po 5 sekundach
        setTimeout(function() {
            notice.fadeOut(function() {
                notice.remove();
            });
        }, 5000);
        
        // Przewiń do góry
        $('html, body').animate({
            scrollTop: $('.wrap').offset().top
        }, 500);
    }
    
    // Dodaj przycisk odświeżania statystyk
    if ($('.stats-grid').length) {
        var refreshButton = $('<button type="button" class="button button-secondary" id="refresh-stats" style="margin-bottom: 15px;">Odśwież statystyki</button>');
        $('.stats-grid').before(refreshButton);
        
        refreshButton.on('click', function() {
            var button = $(this);
            button.prop('disabled', true).text('Odświeżanie...');
            
            // Przeładuj stronę po krótkiej chwili (prosty sposób na odświeżenie statystyk)
            setTimeout(function() {
                window.location.reload();
            }, 1000);
        });
    }
    
    // Dodaj tooltip do pól formularza
    $('#points-amount').attr('title', 'Wprowadź ilość punktów (liczby dziesiętne dozwolone)');
    $('#adjustment-type').attr('title', 'Wybierz typ operacji na punktach użytkownika');
    $('#adjustment-reason').attr('title', 'Opcjonalny powód zmiany (zapisywany w historii)');
    
    // Formatowanie liczb w polach
    $('#points-amount').on('blur', function() {
        var value = parseFloat($(this).val());
        if (!isNaN(value)) {
            $(this).val(value.toFixed(2));
        }
    });
    
    // Skróty klawiszowe
    $(document).on('keydown', function(e) {
        // Ctrl + S = Zapisz (submit formularza)
        if (e.ctrlKey && e.which === 83) {
            e.preventDefault();
            $('#adjust-points-form').submit();
        }
        
        // Ctrl + R = Odśwież statystyki
        if (e.ctrlKey && e.which === 82) {
            e.preventDefault();
            $('#refresh-stats').click();
        }
    });
    
    // Walidacja i podpowiedzi dla różnych typów operacji
    $('#adjustment-type').on('change', function() {
        var type = $(this).val();
        var helpText = '';
        var pointsField = $('#points-amount');
        
        switch(type) {
            case 'add':
                helpText = 'Punkty zostaną dodane do aktualnego salda użytkownika';
                pointsField.attr('placeholder', 'Ile punktów dodać?');
                break;
            case 'subtract':
                helpText = 'Punkty zostaną odjęte od aktualnego salda użytkownika';
                pointsField.attr('placeholder', 'Ile punktów odjąć?');
                break;
            case 'set':
                helpText = 'Saldo użytkownika zostanie ustawione na podaną wartość';
                pointsField.attr('placeholder', 'Na ile ustawić saldo?');
                break;
        }
        
        // Usuń poprzedni help text
        $('.adjustment-help').remove();
        
        // Dodaj nowy help text
        if (helpText) {
            var helpElement = $('<p class="description adjustment-help">' + helpText + '</p>');
            $('#adjustment-type').parent().append(helpElement);
        }
    });
    
    // Inicjalne wywołanie dla ustawienia help text
    $('#adjustment-type').trigger('change');
    
    // Dodaj licznik znaków do pola powodu
    var reasonField = $('#adjustment-reason');
    var maxLength = 500; // Ustaw maksymalną długość
    
    reasonField.attr('maxlength', maxLength);
    
    var charCounter = $('<div class="char-counter" style="text-align: right; font-size: 0.9em; color: #666;"></div>');
    reasonField.after(charCounter);
    
    function updateCharCounter() {
        var currentLength = reasonField.val().length;
        var remaining = maxLength - currentLength;
        var color = remaining < 50 ? '#dc3545' : '#666';
        charCounter.html(currentLength + ' / ' + maxLength + ' znaków').css('color', color);
    }
    
    reasonField.on('input', updateCharCounter);
    updateCharCounter(); // Inicjalne wywołanie
    
    // Dodaj funkcję eksportu danych użytkowników z punktami
    var exportButton = $('<button type="button" class="button button-secondary" id="export-points-data" style="margin-left: 10px;">Eksportuj dane punktów</button>');
    $('#refresh-stats').after(exportButton);
    
    exportButton.on('click', function() {
        var button = $(this);
        button.prop('disabled', true).text('Generowanie...');
        
        // Tworzymy link do pobrania CSV
        var csvContent = "data:text/csv;charset=utf-8,";
        csvContent += "ID użytkownika,Nazwa użytkownika,Email,Punkty\n";
        
        // Zbieramy dane (w prawdziwej implementacji pobralibyśmy to przez AJAX)
        $.ajax({
            url: herbal_admin_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'export_users_points',
                nonce: herbal_admin_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Tworzymy i pobieramy plik CSV
                    var encodedUri = encodeURI("data:text/csv;charset=utf-8," + response.data.csv);
                    var link = document.createElement("a");
                    link.setAttribute("href", encodedUri);
                    link.setAttribute("download", "punkty_uzytkownikow_" + new Date().toISOString().split('T')[0] + ".csv");
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    
                    showNotice('success', 'Plik CSV został pobrany');
                } else {
                    showNotice('error', 'Błąd podczas eksportu: ' + response.data);
                }
            },
            error: function() {
                showNotice('error', 'Błąd połączenia podczas eksportu');
            },
            complete: function() {
                button.prop('disabled', false).text('Eksportuj dane punktów');
            }
        });
    });
});

// Dodatkowe funkcje globalne
window.HerbalPointsAdmin = {
    
    /**
     * Odświeża statystyki bez przeładowania strony
     */
    refreshStats: function() {
        jQuery('.stats-grid').html('<div style="text-align: center; padding: 40px;"><span class="spinner is-active"></span> Ładowanie statystyk...</div>');
        
        jQuery.ajax({
            url: herbal_admin_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'get_points_statistics',
                nonce: herbal_admin_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    jQuery('.stats-grid').html(response.data.html);
                } else {
                    jQuery('.stats-grid').html('<div class="notice notice-error"><p>Błąd ładowania statystyk</p></div>');
                }
            },
            error: function() {
                jQuery('.stats-grid').html('<div class="notice notice-error"><p>Błąd połączenia</p></div>');
            }
        });
    },
    
    /**
     * Masowe dodanie punktów do grupy użytkowników
     */
    bulkAddPoints: function(userIds, points, reason) {
        return jQuery.ajax({
            url: herbal_admin_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'bulk_add_points',
                user_ids: userIds,
                points: points,
                reason: reason,
                nonce: herbal_admin_ajax.nonce
            }
        });
    },
    
    /**
     * Pobiera szczegółową historię punktów użytkownika
     */
    getUserPointsHistory: function(userId, limit, offset) {
        return jQuery.ajax({
            url: herbal_admin_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'get_user_points_history_admin',
                user_id: userId,
                limit: limit || 50,
                offset: offset || 0,
                nonce: herbal_admin_ajax.nonce
            }
        });
    }
};