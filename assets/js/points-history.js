/**
 * JavaScript dla historii punktów w profilu użytkownika
 * Plik: assets/js/points-history.js
 */

jQuery(document).ready(function($) {
    
    // Obsługa przycisku "Wczytaj więcej"
    $('#load-more-history').on('click', function() {
        var button = $(this);
        var offset = parseInt(button.data('offset'));
        var originalText = button.text();
        
        button.prop('disabled', true).text('Ładowanie...');
        
        $.ajax({
            url: herbal_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'get_points_history',
                nonce: herbal_ajax.nonce,
                offset: offset
            },
            success: function(response) {
                if (response.success && response.data.length > 0) {
                    // Dodaj nowe wiersze do tabeli
                    var tbody = $('.herbal-table tbody');
                    
                    response.data.forEach(function(transaction) {
                        var row = createHistoryRow(transaction);
                        tbody.append(row);
                    });
                    
                    // Zaktualizuj offset
                    button.data('offset', offset + 20);
                    button.prop('disabled', false).text(originalText);
                    
                    // Animacja dla nowych wierszy
                    tbody.find('tr').slice(-response.data.length).hide().fadeIn(500);
                    
                } else {
                    // Brak więcej danych
                    button.text('Brak więcej transakcji').addClass('disabled');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', error);
                button.prop('disabled', false).text('Błąd - spróbuj ponownie').addClass('error');
                
                // Przywróć oryginalny tekst po 3 sekundach
                setTimeout(function() {
                    button.text(originalText).removeClass('error');
                }, 3000);
            }
        });
    });
    
    // Funkcja tworząca wiersz historii
    function createHistoryRow(transaction) {
        var changeClass = transaction.points_change >= 0 ? 'positive' : 'negative';
        var changePrefix = transaction.points_change >= 0 ? '+' : '';
        var changeIcon = transaction.points_change >= 0 ? '↗️' : '↘️';
        
        var row = $('<tr>').addClass(changeClass);
        
        // Kolumna daty
        var dateCell = $('<td>').addClass('date-column').text(formatDate(transaction.created_at));
        
        // Kolumna typu transakcji
        var typeCell = $('<td>').addClass('type-column').html(getTransactionTypeLabel(transaction.transaction_type));
        
        // Kolumna zmiany punktów
        var changeCell = $('<td>').addClass('change-column');
        var changeSpan = $('<span>')
            .addClass('points-change ' + changeClass)
            .html(changeIcon + ' ' + changePrefix + parseFloat(transaction.points_change).toFixed(2));
        changeCell.append(changeSpan);
        
        // Kolumna salda
        var balanceCell = $('<td>').addClass('balance-column').text(parseFloat(transaction.points_after).toFixed(2));
        
        // Kolumna szczegółów
        var detailsCell = $('<td>').addClass('details-column').html(getTransactionDetails(transaction));
        
        row.append(dateCell, typeCell, changeCell, balanceCell, detailsCell);
        
        return row;
    }
    
    // Formatowanie daty
    function formatDate(dateString) {
        var date = new Date(dateString);
        var options = {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit'
        };
        return date.toLocaleDateString('pl-PL', options);
    }
    
    // Etykiety typów transakcji
    function getTransactionTypeLabel(type) {
        var labels = {
            'purchase': '🛒 Zakup',
            'order_payment': '💳 Płatność za zamówienie',
            'mix_sale_commission': '💡 Prowizja za mieszankę',
            'manual': '✋ Dodane ręcznie',
            'admin_adjustment': '⚙️ Korekta administratora',
            'bonus': '🎁 Bonus',
            'refund': '↩️ Zwrot',
            'registration_bonus': '🎉 Bonus rejestracyjny',
            'review_bonus': '⭐ Bonus za recenzję'
        };
        return labels[type] || type;
    }
    
    // Szczegóły transakcji
    function getTransactionDetails(transaction) {
        if (!transaction.reference_id) {
            return '-';
        }
        
        switch (transaction.transaction_type) {
            case 'purchase':
            case 'order_payment':
                return '<a href="#" onclick="showOrderDetails(' + transaction.reference_id + ')" title="Kliknij aby zobaczyć szczegóły">Zamówienie #' + transaction.reference_id + '</a>';
            case 'mix_sale_commission':
                return 'Prowizja z mieszanki';
            case 'refund':
                return 'Zwrot za zamówienie #' + transaction.reference_id;
            default:
                return 'ID: ' + transaction.reference_id;
        }
    }
    
    // Filtrowanie historii
    var filterForm = $('<div class="points-filter-section"></div>');
    var filterHTML = `
        <h4>Filtruj historię</h4>
        <div class="filter-controls">
            <select id="transaction-type-filter">
                <option value="">Wszystkie typy</option>
                <option value="purchase">Zakupy</option>
                <option value="order_payment">Płatności</option>
                <option value="mix_sale_commission">Prowizje</option>
                <option value="bonus">Bonusy</option>
                <option value="refund">Zwroty</option>
            </select>
            
            <select id="points-direction-filter">
                <option value="">Wszystkie</option>
                <option value="positive">Tylko dodatnie</option>
                <option value="negative">Tylko ujemne</option>
            </select>
            
            <input type="date" id="date-from-filter" placeholder="Data od">
            <input type="date" id="date-to-filter" placeholder="Data do">
            
            <button type="button" id="apply-filters" class="herbal-button-secondary">Zastosuj filtry</button>
            <button type="button" id="clear-filters" class="herbal-button-secondary">Wyczyść</button>
        </div>
    `;
    
    filterForm.html(filterHTML);
    
    // Dodaj filtry przed tabelą historii
    if ($('.points-history-table').length) {
        $('.points-history-table').before(filterForm);
    }
    
    // Obsługa filtrów
    $('#apply-filters').on('click', function() {
        var typeFilter = $('#transaction-type-filter').val();
        var directionFilter = $('#points-direction-filter').val();
        var dateFromFilter = $('#date-from-filter').val();
        var dateToFilter = $('#date-to-filter').val();
        
        $('.herbal-table tbody tr').each(function() {
            var row = $(this);
            var showRow = true;
            
            // Filtr typu transakcji
            if (typeFilter) {
                var rowType = row.find('.type-column').text().toLowerCase();
                if (!rowType.includes(getTransactionTypeLabel(typeFilter).toLowerCase().substr(2))) {
                    showRow = false;
                }
            }
            
            // Filtr kierunku punktów
            if (directionFilter) {
                var isPositive = row.hasClass('positive');
                if ((directionFilter === 'positive' && !isPositive) || 
                    (directionFilter === 'negative' && isPositive)) {
                    showRow = false;
                }
            }
            
            // Filtr dat (uproszczona implementacja)
            if (dateFromFilter || dateToFilter) {
                var rowDateText = row.find('.date-column').text();
                var rowDate = new Date(rowDateText.split(' ')[0].split('.').reverse().join('-'));
                
                if (dateFromFilter && rowDate < new Date(dateFromFilter)) {
                    showRow = false;
                }
                if (dateToFilter && rowDate > new Date(dateToFilter)) {
                    showRow = false;
                }
            }
            
            // Pokaż/ukryj wiersz
            if (showRow) {
                row.show();
            } else {
                row.hide();
            }
        });
        
        // Sprawdź czy są widoczne wiersze
        var visibleRows = $('.herbal-table tbody tr:visible').length;
        if (visibleRows === 0) {
            if (!$('.no-results-message').length) {
                $('.herbal-table tbody').append(
                    '<tr class="no-results-message"><td colspan="5" style="text-align: center; padding: 40px; color: #666;">Brak wyników spełniających kryteria filtrowania</td></tr>'
                );
            }
        } else {
            $('.no-results-message').remove();
        }
    });
    
    // Wyczyść filtry
    $('#clear-filters').on('click', function() {
        $('#transaction-type-filter').val('');
        $('#points-direction-filter').val('');
        $('#date-from-filter').val('');
        $('#date-to-filter').val('');
        $('.herbal-table tbody tr').show();
        $('.no-results-message').remove();
    });
    
    // Obsługa klawisza Enter w filtrach
    $('.filter-controls input, .filter-controls select').on('keypress', function(e) {
        if (e.which === 13) {
            $('#apply-filters').click();
        }
    });
    
    // Eksport historii do CSV
    var exportButton = $('<button type="button" class="herbal-button-secondary export-history-btn">📊 Eksportuj historię</button>');
    $('.load-more-section').append(exportButton);
    
    exportButton.on('click', function() {
        var button = $(this);
        var originalText = button.text();
        button.prop('disabled', true).text('Eksportowanie...');
        
        // Zbierz dane z tabeli
        var csvData = [];
        csvData.push(['Data', 'Typ transakcji', 'Zmiana punktów', 'Saldo po', 'Szczegóły']);
        
        $('.herbal-table tbody tr:visible').each(function() {
            var row = $(this);
            if (!row.hasClass('no-results-message')) {
                var rowData = [];
                row.find('td').each(function(index) {
                    if (index < 5) { // Tylko 5 kolumn
                        rowData.push($(this).text().replace(/,/g, ';')); // Zamień przecinki na średniki
                    }
                });
                csvData.push(rowData);
            }
        });
        
        // Stwórz i pobierz CSV
        var csvContent = csvData.map(row => row.join(',')).join('\n');
        var blob = new Blob(['\ufeff' + csvContent], { type: 'text/csv;charset=utf-8;' });
        var link = document.createElement('a');
        var url = URL.createObjectURL(blob);
        link.setAttribute('href', url);
        link.setAttribute('download', 'historia_punktow_' + new Date().toISOString().split('T')[0] + '.csv');
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        
        button.prop('disabled', false).text(originalText);
    });
    
    // Tooltips dla transakcji
    $('.herbal-table tbody tr').each(function() {
        var row = $(this);
        var changeElement = row.find('.points-change');
        var pointsValue = parseFloat(changeElement.text().replace(/[^\d.-]/g, ''));
        
        if (pointsValue > 0) {
            changeElement.attr('title', 'Punkty zostały dodane do Twojego konta');
        } else {
            changeElement.attr('title', 'Punkty zostały odjęte z Twojego konta');
        }
    });
    
    // Animacja licznika punktów na karcie balansu
    function animatePointsCounter() {
        var pointsNumber = $('.points-number');
        if (pointsNumber.length) {
            var finalValue = parseFloat(pointsNumber.text().replace(/,/g, ''));
            var startValue = 0;
            var duration = 1500;
            var startTime = null;
            
            function animate(currentTime) {
                if (startTime === null) startTime = currentTime;
                var timeElapsed = currentTime - startTime;
                var progress = Math.min(timeElapsed / duration, 1);
                
                // Easing function (ease-out)
                var easedProgress = 1 - Math.pow(1 - progress, 3);
                var currentValue = startValue + (finalValue * easedProgress);
                
                pointsNumber.text(currentValue.toLocaleString('pl-PL', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                }));
                
                if (progress < 1) {
                    requestAnimationFrame(animate);
                }
            }
            
            requestAnimationFrame(animate);
        }
    }
    
    // Uruchom animację po załadowaniu strony
    setTimeout(animatePointsCounter, 300);
    
    // Responsive handling
    function handleResponsive() {
        if ($(window).width() < 768) {
            // Na małych ekranach ukryj niektóre kolumny
            $('.herbal-table th:nth-child(4), .herbal-table td:nth-child(4)').addClass('hidden-mobile');
            $('.herbal-table th:nth-child(5), .herbal-table td:nth-child(5)').addClass('hidden-mobile');
        } else {
            $('.hidden-mobile').removeClass('hidden-mobile');
        }
    }
    
    handleResponsive();
    $(window).resize(handleResponsive);
});

// Funkcja globalna do pokazywania szczegółów zamówienia
window.showOrderDetails = function(orderId) {
    // Modal z szczegółami zamówienia
    var modal = $('<div class="herbal-modal-overlay"></div>');
    var modalContent = $('<div class="herbal-modal-content"></div>');
    
    modalContent.html(`
        <div class="modal-header">
            <h3>Szczegóły zamówienia #${orderId}</h3>
            <button class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <div class="loading">Ładowanie szczegółów...</div>
        </div>
    `);
    
    modal.append(modalContent);
    $('body').append(modal);
    
    // Załaduj szczegóły zamówienia
    $.ajax({
        url: herbal_ajax.ajax_url,
        type: 'POST',
        data: {
            action: 'get_order_details',
            order_id: orderId,
            nonce: herbal_ajax.nonce
        },
        success: function(response) {
            if (response.success) {
                modalContent.find('.modal-body').html(response.data.html);
            } else {
                modalContent.find('.modal-body').html('<p>Błąd ładowania szczegółów zamówienia.</p>');
            }
        },
        error: function() {
            modalContent.find('.modal-body').html('<p>Błąd połączenia.</p>');
        }
    });
    
    // Obsługa zamykania modala
    modal.on('click', function(e) {
        if (e.target === modal[0] || $(e.target).hasClass('modal-close')) {
            modal.remove();
        }
    });
    
    // ESC key
    $(document).on('keydown.modal', function(e) {
        if (e.keyCode === 27) {
            modal.remove();
            $(document).off('keydown.modal');
        }
    });
};

// Style dla filtrów i responsywności
$('<style>').text(`
    .points-filter-section {
        background: #f8f9fa;
        padding: 20px;
        border-radius: 8px;
        margin-bottom: 20px;
        border: 1px solid #dee2e6;
    }
    
    .points-filter-section h4 {
        margin: 0 0 15px 0;
        color: #495057;
    }
    
    .filter-controls {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 10px;
        align-items: end;
    }
    
    .filter-controls select,
    .filter-controls input {
        padding: 8px 12px;
        border: 1px solid #ced4da;
        border-radius: 4px;
        font-size: 0.9em;
    }
    
    .export-history-btn {
        margin-left: 10px;
    }
    
    .herbal-modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
        z-index: 9999;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .herbal-modal-content {
        background: white;
        border-radius: 8px;
        max-width: 600px;
        max-height: 80vh;
        overflow-y: auto;
        box-shadow: 0 10px 30px rgba(0,0,0,0.3);
    }
    
    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 20px;
        border-bottom: 1px solid #dee2e6;
    }
    
    .modal-header h3 {
        margin: 0;
    }
    
    .modal-close {
        background: none;
        border: none;
        font-size: 24px;
        cursor: pointer;
        padding: 0;
        width: 30px;
        height: 30px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .modal-body {
        padding: 20px;
    }
    
    @media (max-width: 768px) {
        .hidden-mobile {
            display: none !important;
        }
        
        .filter-controls {
            grid-template-columns: 1fr;
        }
        
        .herbal-modal-content {
            margin: 20px;
            max-width: calc(100% - 40px);
        }
    }
`).appendTo('head');