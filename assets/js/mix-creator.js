document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('herbal-mix-form');
  const packagingItems = document.querySelectorAll('.packaging-item');
  const searchInput = document.getElementById('ingredient-search');
  const categoriesContainer = document.getElementById('categories-container');
  const ingredientsContainer = document.getElementById('ingredients-container');
  const selectedContainer = document.getElementById('selected-ingredients');
  const totalWeightEl = document.getElementById('total-weight');
  const totalPriceEl = document.getElementById('total-price');
  const totalPointsCostEl = document.getElementById('total-points-cost');
  const totalPointsEarnedEl = document.getElementById('total-points-earned');
  const mixNameInput = document.getElementById('mix-name');
  const saveMixBtn = document.getElementById('save-mix-btn');
  const addToBasketBtn = document.getElementById('add-to-basket-btn');
  
  // Add references to section containers (for disabling/enabling)
  const categoriesSection = document.querySelector('.categories-section');
  const ingredientsSection = document.querySelector('.ingredients-section');

  // Dodaj nasłuchiwacze zdarzeń dla pola nazwy mieszanki
  mixNameInput.addEventListener('focus', function() {
    // Resetuj placeholder do domyślnego, jeśli został zmieniony
    if (this.placeholder === 'Please enter a mix name') {
      this.placeholder = 'Enter mix name';
    }
  });

  mixNameInput.addEventListener('input', function() {
    // Usuń klasę wymaganego pola, gdy użytkownik wpisuje coś
    this.classList.remove('required-field');
    document.querySelector('.mix-name-input').classList.remove('show-required-hint');
    
    // Aktualizuj stan przycisków
    updateFormButtons();
  });

  let selectedCapacity = 0;
  let selectedPrice = 0;
  let selectedPricePoint = 0;
  let selectedPointEarned = 0;
  let loadedIngredients = [];
  let selectedIngredients = [];
  let selectedPackagingId = 0;
  let selectedPackagingName = "";

  // Initialize Chart.js doughnut
  const ctx = document.getElementById('mixChart').getContext('2d');
  // Znajdź inicjalizację wykresu (około linia 57-74)
const mixChart = new Chart(ctx, {
  type: 'doughnut',
  data: { labels: ['Free space'], datasets: [{ data: [100], backgroundColor: ['#e0e0e0'] }] },
  options: { 
    cutout: '70%', 
    responsive: true, 
    // Dodaj te opcje:
    devicePixelRatio: window.devicePixelRatio || 1, // Poprawia ostrość na ekranach o wysokiej rozdzielczości
    plugins: { 
      legend: { 
        display: true, 
        position: 'bottom',
        labels: {
          boxWidth: 12,
          padding: 10,
          font: {
            family: 'Arial, sans-serif', // Lepszy font
            size: 12,
            weight: 'bold' // Pogrubiona czcionka będzie wyraźniejsza
          },
          // Dodajemy indywidualny renderowanie etykiet
          generateLabels: function(chart) {
            // Niestandardowa funkcja generowania etykiet
            const data = chart.data;
            if (data.labels.length && data.datasets.length) {
              return data.labels.map(function(label, i) {
                const meta = chart.getDatasetMeta(0);
                const style = meta.controller.getStyle(i);
                return {
                  text: label,
                  fillStyle: style.backgroundColor,
                  strokeStyle: style.borderColor,
                  lineWidth: style.borderWidth,
                  hidden: isNaN(data.datasets[0].data[i]) || meta.data[i].hidden,
                  index: i
                };
              });
            }
            return [];
          }
        }
      },
      tooltip: {
        callbacks: {
          label: function(context) {
            return `${context.label}: ${context.raw}g`;
          }
        }
      }
    }
  }
});

  // Package selection
  packagingItems.forEach(item => {
    item.addEventListener('click', () => {
      packagingItems.forEach(el => el.classList.remove('selected'));
      item.classList.add('selected');

      selectedCapacity = Number(item.dataset.capacity) || 0;
      selectedPrice = Number(item.dataset.price) || 0;
      selectedPricePoint = Number(item.dataset.pricePoint) || 0;
      selectedPointEarned = Number(item.dataset.pointEarned) || 0;
      selectedPackagingId = Number(item.dataset.id) || 0;
      selectedPackagingName = item.querySelector('h3')?.textContent || 'Custom Package';
      
      searchInput.disabled = false;
      
      // Remove disabled class from the parent section elements
      categoriesSection.classList.remove('disabled');
      ingredientsSection.classList.remove('disabled');

      // Reset ingredients and chart
      selectedIngredients = [];
      renderSelected();
      updateChart();
      updateFormButtons();

      loadCategories();
    });
  });

  // Load categories
  function loadCategories() {
    fetch(`${herbalMixData.ajax_url}?action=get_herbal_categories&nonce=${herbalMixData.nonce}`)
      .then(res => res.json())
      .then(json => {
        if (json.success) renderCategories(json.data);
      }).catch(err => {
        console.error('Error loading categories:', err);
      });
  }

  function renderCategories(categories) {
    categoriesContainer.innerHTML = '';
    const ul = document.createElement('ul'); ul.className = 'categories-list';

    // All category
    const allLi = document.createElement('li');
    allLi.className = 'category-item selected';
    allLi.textContent = 'All';
    allLi.dataset.id = 0;
    ul.appendChild(allLi);

    categories.forEach(cat => {
      const li = document.createElement('li'); li.className = 'category-item';
      li.textContent = cat.name;
      li.title = cat.description;
      li.dataset.id = cat.id;
      ul.appendChild(li);
    });

    categoriesContainer.appendChild(ul);

    ul.addEventListener('click', (e) => {
      if (!e.target.classList.contains('category-item')) return;
      ul.querySelectorAll('.category-item').forEach(li => li.classList.remove('selected'));
      e.target.classList.add('selected');
      loadIngredients(e.target.dataset.id);
    });

    loadIngredients(0);
  }

  // Load ingredients - oryginalny kod
  function loadIngredients(categoryId) {
    fetch(
      `${herbalMixData.ajax_url}?action=load_herbal_ingredients&nonce=${herbalMixData.nonce}` +
      `&category_id=${categoryId}&packaging_capacity=${selectedCapacity}`
    )
    .then(res => res.json())
    .then(json => {
      if (json.success) {
        // Map data to ensure id is a number
        loadedIngredients = json.data.map(item => ({
          id: Number(item.id),
          name: item.name,
          image_url: item.image_url,
          price: Number(item.price || 0),
          price_point: Number(item.price_point || 0),
          point_earned: Number(item.point_earned || 0),
          is_available: Boolean(item.is_available)
        }));
        renderIngredients(loadedIngredients);
      }
    })
    .catch(err => {
      console.error('Error loading ingredients:', err);
    });
  }

  // Oryginalna funkcja renderIngredients

// Zaktualizowana funkcja renderIngredients z nową strukturą HTML
function renderIngredients(items) {
  ingredientsContainer.innerHTML = '';
  const ul = document.createElement('ul'); ul.className = 'ingredients-list';

  items.forEach(item => {
    const li = document.createElement('li'); 
    li.className = 'ingredient-item ingredient-tooltip';
    
    // Check if already selected or not available
    const isSelected = selectedIngredients.some(i => i.id === item.id);
    const isDisabled = !item.is_available || isSelected;
    
    if (!item.is_available) {
      li.classList.add('disabled');
    }
    
    // Dodajemy podstawowe informacje o składniku
    // ZMIANA: Przebudowujemy strukturę elementów, używając div-ów dla lepszego layoutu
    let htmlContent = `
      <div class="ingredient-thumb-container">
        <img src="${item.image_url || ''}" alt="${item.name}" class="ingredient-thumb">
      </div>
      <div class="ingredient-content">
        <h4>${item.name}</h4>
      </div>
      <div class="ingredient-actions">
        <button type="button" class="add-ingredient-btn" data-id="${item.id}" 
          ${isDisabled ? 'disabled' : ''}>
          ${isSelected ? 'Added' : 'Add'}
        </button>
        <div class="info-icon" data-id="${item.id}">i</div>
      </div>
    `;
    
    // Dodajemy tooltip z dodatkowymi informacjami (ukryty)
    htmlContent += `
      <div class="ingredient-tooltip-content" style="display: none;">
        <div class="ingredient-tooltip-header">
          <div class="ingredient-tooltip-title">${item.name}</div>
          <div class="ingredient-tooltip-close">×</div>
        </div>
        <img src="${item.image_url || ''}" alt="${item.name}" class="ingredient-tooltip-image">
        <div class="ingredient-tooltip-description">${item.description || 'No description available.'}</div>
        ${item.story ? `<div class="ingredient-tooltip-label">Story:</div><div class="ingredient-tooltip-story">${item.story}</div>` : ''}
        ${item.contraindications ? `<div class="ingredient-tooltip-label">Contraindications:</div><div>${item.contraindications}</div>` : ''}
        ${item.do_not_mix_with ? `<div class="ingredient-tooltip-label">Do not mix with:</div><div>${item.do_not_mix_with}</div>` : ''}
      </div>
    `;
    
    li.innerHTML = htmlContent;
    ul.appendChild(li);
  });

  ingredientsContainer.appendChild(ul);
  
  // Dodajemy obsługę tooltipów
  setupTooltips();
}

// Funkcja zamykająca wszystkie aktywne tooltopy
function closeAllTooltips() {
  // Usuń klasę active ze wszystkich ikon
  document.querySelectorAll('.info-icon.active').forEach(icon => {
    icon.classList.remove('active');
  });
  
  // Usuń wszystkie backdropy
  document.querySelectorAll('.modal-backdrop, .tooltip-backdrop').forEach(el => {
    el.remove();
  });
  
  // Usuń wszystkie aktywne tooltopy
  document.querySelectorAll('.active-tooltip').forEach(el => {
    el.remove();
  });
}

// Funkcja inicjalizująca tooltopy
// Poprawiona funkcja setupTooltips - eliminuje problem z niewidocznym tooltipem
function setupTooltips() {
  // Obsługa tooltipów na wszystkich urządzeniach
  const infoIcons = document.querySelectorAll('.info-icon');
  
  infoIcons.forEach(icon => {
    // Obsługa kliknięcia ikony informacyjnej
    icon.addEventListener('click', function(e) {
      e.stopPropagation();
      e.preventDefault();
      
      // Zamknij wszystkie inne otwarte tooltopy
      document.querySelectorAll('.info-icon.active').forEach(activeIcon => {
        if (activeIcon !== icon) {
          activeIcon.classList.remove('active');
        }
      });
      
      // Usuń wszystkie backdropy i aktywne tooltopy
      document.querySelectorAll('.modal-backdrop, .tooltip-backdrop, .active-tooltip').forEach(el => {
        el.remove();
      });
      
      // Przełącz stan aktywnego tooltipa
      this.classList.toggle('active');
      
      // Jeśli tooltip jest teraz aktywny, dodaj backdrop
      if (this.classList.contains('active')) {
        // Znajdź tooltip content
        const tooltipContent = this.parentNode.parentNode.querySelector('.ingredient-tooltip-content');
        
        // Sprawdź czy tooltip content istnieje
        if (tooltipContent) {
          // Dodaj backdrop
          const backdrop = document.createElement('div');
          backdrop.className = 'modal-backdrop fixed';
          document.body.appendChild(backdrop);
          
          // Klonuj tooltip content i dodaj go do body
          const tooltip = tooltipContent.cloneNode(true);
          tooltip.classList.add('active-tooltip');
          tooltip.style.display = 'block';
          document.body.appendChild(tooltip);
          
          // Poprawka: Upewnij się, że tooltip jest widoczny i ma właściwe style
          tooltip.style.backgroundColor = 'white';
          tooltip.style.zIndex = '1000';
          
          // Dodaj przycisk zamknięcia jeśli jeszcze nie istnieje
          if (!tooltip.querySelector('.ingredient-tooltip-close')) {
            const closeBtn = document.createElement('div');
            closeBtn.className = 'ingredient-tooltip-close';
            closeBtn.textContent = '×';
            closeBtn.addEventListener('click', closeAllTooltips);
            tooltip.appendChild(closeBtn);
          }
          
          // Dodaj listener do przycisku zamknięcia
          const closeBtn = tooltip.querySelector('.ingredient-tooltip-close');
          if (closeBtn) {
            closeBtn.addEventListener('click', closeAllTooltips);
          }
          
          // Dodaj listener do backdropu
          backdrop.addEventListener('click', closeAllTooltips);
          
          // Dla debugowania - dodaj bezpośrednio info do konsoli
          console.log('Tooltip wyświetlony', tooltip);
        } else {
          console.error('Nie znaleziono elementu tooltip-content dla tego składnika');
        }
      }
    });
  });
  
  // Obsługa zamykania tooltipów po kliknięciu poza nimi
  document.addEventListener('click', function(e) {
    if (!e.target.closest('.active-tooltip') && 
        !e.target.classList.contains('info-icon') && 
        !e.target.closest('.ingredient-tooltip-content')) {
      closeAllTooltips();
    }
  });
}
// Funkcja do dynamicznego pobierania pełnych danych składnika
function fetchIngredientDetails(ingredientId) {
  return fetch(
    `${herbalMixData.ajax_url}?action=get_ingredient_details&nonce=${herbalMixData.nonce}&ingredient_id=${ingredientId}`
  )
  .then(res => res.json())
  .then(json => {
    if (json.success) {
      return json.data;
    } else {
      console.error('Error fetching ingredient details:', json.data);
      return null;
    }
  })
  .catch(err => {
    console.error('Error:', err);
    return null;
  });
}

// Aktualizacja event listenera dla responsywności
window.addEventListener('resize', function() {
  const isMobile = window.innerWidth <= 768;
  
  // Zamknij wszystkie tooltopy przy zmianie rozmiaru ekranu
  document.querySelectorAll('.info-icon.active').forEach(icon => {
    icon.classList.remove('active');
  });
  
  const backdrop = document.querySelector('.modal-backdrop');
  if (backdrop) {
    backdrop.remove();
  }
});

  // Search functionality
  searchInput.addEventListener('input', (e) => {
    const query = e.target.value.toLowerCase().trim();
    if (!loadedIngredients.length) return;
    
    const filtered = query ? 
      loadedIngredients.filter(item => item.name.toLowerCase().includes(query)) : 
      loadedIngredients;
    
    renderIngredients(filtered);
  });

  // Add ingredient
  ingredientsContainer.addEventListener('click', (e) => {
    if (e.target.matches('.add-ingredient-btn') && !e.target.disabled) {
      e.preventDefault();
      const id = Number(e.target.dataset.id);
      const item = loadedIngredients.find(i => i.id === id);
      
      if (!item || selectedIngredients.some(i => i.id === id)) return;
      
      // Calculate default weight based on remaining capacity
      const usedWeight = selectedIngredients.reduce((sum, ing) => sum + ing.weight, 0);
      const remaining = selectedCapacity - usedWeight;
      const defaultWeight = Math.min(Math.ceil(selectedCapacity / 10), remaining);
      
      if (defaultWeight <= 0) {
        alert('No capacity left in the package. Remove some ingredients first.');
        return;
      }
      
      selectedIngredients.push({
        id,
        name: item.name,
        price: Number(item.price) || 0,
        price_point: Number(item.price_point) || 0,
        point_earned: Number(item.point_earned) || 0,
        weight: defaultWeight,
        image_url: item.image_url || ''
      });
      
      // Update UI
      e.target.textContent = 'Added';
      e.target.disabled = true;
      
      renderSelected();
      updateChart();
      updateFormButtons();
    }
  });

  // Render selected ingredients
  function renderSelected() {
    selectedContainer.innerHTML = '';
    
    if (selectedIngredients.length === 0) {
      selectedContainer.innerHTML = '<p class="no-ingredients">No ingredients selected yet.</p>';
      return;
    }
    
    // Create ingredients elements
    selectedIngredients.forEach(ing => {
      const div = document.createElement('div'); 
      div.className = 'selected-ingredient';
      
      // Utwórz kontener dla suwaka i przycisku
      div.innerHTML = `
        <div class="selected-ingredient-info">
    <img src="${ing.image_url || ''}" alt="${ing.name}" class="selected-thumb">
    <span class="ingredient-name">${ing.name}</span>
  </div>
  <div class="slider-container">
    <input type="range" min="1" max="${selectedCapacity}" step="1" value="${ing.weight}" 
      class="ingredient-slider" data-id="${ing.id}" data-original-weight="${ing.weight}">
    <span class="weight-label">${ing.weight}g</span>
  </div>
        <div class="selected-ingredient-price">
          <span class="price-label">${(ing.price * ing.weight).toFixed(2)} ${herbalMixData.currency_symbol}</span>
        </div>
        <button type="button" class="remove-ingredient-btn" data-id="${ing.id}">&times;</button>
      `;
      
      selectedContainer.appendChild(div);
      
      // Dodaj bezpośredni event listener do suwaka, aby zapewnić poprawne działanie
      const slider = div.querySelector('.ingredient-slider');
      if (slider) {
        slider.addEventListener('input', function() {
  const id = Number(this.dataset.id);
  const ing = selectedIngredients.find(i => i.id === id);
  if (!ing) return;
  
  const newWeight = Number(this.value);
  const oldWeight = ing.weight;
  
  // Calculate total weight after change - używamy bardziej precyzyjnych obliczeń
  let totalWeight = 0;
  for (const item of selectedIngredients) {
    totalWeight += (item.id === id) ? newWeight : item.weight;
  }
  
  // Rozwiązanie problemu z zaokrąglaniem - używamy epsilon do porównania
  const epsilon = 0.001; // Tolerancja dla błędów zaokrąglania
  if (totalWeight > selectedCapacity + epsilon) {
    // Zamiast resetować do starej wartości, ustawiamy maksymalną możliwą
    const maxPossible = oldWeight + (selectedCapacity - 
      selectedIngredients.reduce((sum, i) => sum + i.weight, 0));
    
    // Upewniamy się, że wartość jest liczbą całkowitą
    const maxPossibleInt = Math.floor(maxPossible);
    
    if (maxPossibleInt > oldWeight) {
      this.value = maxPossibleInt;
      ing.weight = maxPossibleInt;
    } else {
      this.value = oldWeight; // Jeśli nie można zwiększyć, zostaw jak jest
      return;
    }
  } else {
    // Jeśli nowa waga jest akceptowalna, aktualizujemy ją normalnie
    ing.weight = newWeight;
  }
  
  // Update UI
  const weightLabel = this.parentNode.querySelector('.weight-label');
  if (weightLabel) weightLabel.textContent = `${ing.weight}g`;
  
  const priceLabel = this.closest('.selected-ingredient').querySelector('.price-label');
  if (priceLabel) priceLabel.textContent = `${(ing.price * ing.weight).toFixed(2)} ${herbalMixData.currency_symbol}`;
  
  updateChart();
  updateFormButtons();
});
      }
    });
  }

  // Remove ingredient
  selectedContainer.addEventListener('click', (e) => {
    if (e.target.matches('.remove-ingredient-btn')) {
      const id = Number(e.target.dataset.id);
      
      // Remove from array
      selectedIngredients = selectedIngredients.filter(i => i.id !== id);
      
      // Update ingredient button status
      const addBtn = ingredientsContainer.querySelector(`.add-ingredient-btn[data-id="${id}"]`);
      if (addBtn) {
        addBtn.disabled = false;
        addBtn.textContent = 'Add';
      }
      
      renderSelected();
      updateChart();
      updateFormButtons();
    }
  });

  // Update chart and summary
function updateChart() {
  // If no packaging selected, reset chart and summary
  if (!selectedCapacity) {
    mixChart.data.labels = ['Select packaging'];
    mixChart.data.datasets[0].data = [100];
    mixChart.data.datasets[0].backgroundColor = ['#e0e0e0'];
    mixChart.update();

    totalWeightEl.textContent = '0g';
    totalPriceEl.textContent = '0.00';
    totalPointsCostEl.textContent = '0';
    totalPointsEarnedEl.textContent = '0';
    return;
  }

  // Compute used and remaining weights - Poprawione obliczanie wykorzystanej wagi
  const weights = selectedIngredients.map(i => i.weight);
  
  // Użyj bardziej precyzyjnego podejścia do obliczania sumy
  let used = 0;
  for (const weight of weights) {
    used += weight;
  }
  // Upewnij się, że wynik jest zaokrąglony do pełnych gramów
  used = Math.round(used);
  
  const remaining = Math.max(0, selectedCapacity - used);

  // Build chart data
  const labels = selectedIngredients.map(i => i.name);
  const data = [...weights];
  const colors = selectedIngredients.map((_, idx) =>
    `hsl(${idx * (360 / (selectedIngredients.length || 1))},70%,50%)`
  );

  if (remaining > 0) {
    labels.push('Free space');
    data.push(remaining);
    colors.push('#e0e0e0');
  }

  mixChart.data.labels = labels;
  mixChart.data.datasets[0].data = data;
  mixChart.data.datasets[0].backgroundColor = colors;
  
  // Zapobiegaj rozmazywaniu tekstu w wykresie
  mixChart.options.plugins.legend.labels.font = {
    family: 'Arial, sans-serif',
    size: 12,
    weight: 'bold'
  };
  
  mixChart.options.devicePixelRatio = window.devicePixelRatio || 1;
  
  mixChart.update();

  // Update total weight display - pokazuj dokładne wartości
  totalWeightEl.textContent = `${used}g / ${selectedCapacity}g`;

  // Calculate total price (currency)
  const priceSum = selectedIngredients.reduce((sum, i) =>
    sum + i.price * i.weight, 0
  ) + selectedPrice;
  totalPriceEl.textContent = priceSum.toFixed(2);

  // Calculate cost in points (ingredients + packaging)
  const costPoints = selectedIngredients.reduce((sum, i) =>
    sum + i.price_point * i.weight, 0
  ) + selectedPricePoint;
  totalPointsCostEl.textContent = costPoints.toFixed(0);

  // Calculate reward points earned for this blend
  const earnPoints = selectedIngredients.reduce((sum, i) =>
    sum + i.point_earned * i.weight, 0
  ) + selectedPointEarned;
  totalPointsEarnedEl.textContent = earnPoints.toFixed(0);
}

  // Enable/disable form action buttons based on selection status
  function updateFormButtons() {
    const hasName = mixNameInput.value.trim().length > 0;
    const hasIngredients = selectedIngredients.length > 0;
    const hasPackaging = selectedCapacity > 0;
    
    // Enable/disable save button
    saveMixBtn.disabled = !(hasIngredients && hasPackaging);
    
    // Enable/disable add to basket button - wymagamy również nazwy mieszanki
    addToBasketBtn.disabled = !(hasIngredients && hasPackaging && hasName);
    
    // Opcjonalnie: dodaj klasę wskazującą na wymagane pole, jeśli wszystko jest wybrane poza nazwą
    if (hasIngredients && hasPackaging && !hasName) {
        mixNameInput.classList.add('required-field');
    } else {
        mixNameInput.classList.remove('required-field');
    }
  }
  
  // Button action listeners
  saveMixBtn.addEventListener('click', (e) => {
    e.preventDefault();
    if (saveMixBtn.disabled) return;
    
    // Add action type
    const formData = new FormData();
    formData.append('action', 'save_mix');
    formData.append('nonce', herbalMixData.nonce);
    formData.append('mix_name', mixNameInput.value || 'Custom Mix');
    
    // Przygotuj uproszczone dane do zapisu - tylko potrzebne informacje
    formData.append('mix_data', JSON.stringify({
      user: {
        id: herbalMixData.user_id,
        name: herbalMixData.user_name
      },
      action: 'save_favorite',
      packaging: {
        id: selectedPackagingId,
        name: selectedPackagingName,
        capacity: selectedCapacity,
        price: selectedPrice,
        price_point: selectedPricePoint,
        point_earned: selectedPointEarned
      },
      ingredients: selectedIngredients.map(ing => ({
        id: ing.id,
        name: ing.name,
        weight: ing.weight,
        price: ing.price,
        price_point: ing.price_point,
        point_earned: ing.point_earned
      }))
    }));
    
    fetch(herbalMixData.ajax_url, {
      method: 'POST',
      body: formData
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        alert('Your mix has been saved successfully!');
        // Opcjonalnie: przekieruj do strony profilu użytkownika
        // window.location.href = '/user-profile/';
        
        // Alternatywnie: odśwież stronę
        location.reload();
      } else {
        alert('Error saving mix: ' + (data.data || 'Unknown error'));
      }
    })
    .catch(error => {
      console.error('Error:', error);
      alert('Error saving mix. Please try again.');
    });
  });
  
  addToBasketBtn.addEventListener('click', (e) => {
    e.preventDefault();
    
    // Sprawdź czy nazwa mieszanki została wprowadzona
    const mixName = mixNameInput.value.trim();
    if (!mixName) {
      // Podświetl pole nazwy jako wymagane
      mixNameInput.classList.add('required-field');
      mixNameInput.placeholder = 'Please enter a mix name'; // Zmień placeholder
      document.querySelector('.mix-name-input').classList.add('show-required-hint');
      
      // Przesuń widok do pola nazwy mieszanki
      mixNameInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
      mixNameInput.focus();
      
      // Przerwij wykonanie funkcji
      return;
    }
    
    if (addToBasketBtn.disabled) return;
    
    // Pokazanie informacji o ładowaniu
    addToBasketBtn.textContent = 'Adding to cart...';
    addToBasketBtn.disabled = true;
    
    // Przygotowanie danych mieszanki
    const formData = new FormData();
    formData.append('action', 'add_to_basket'); // Zmiana nazwy akcji AJAX
    formData.append('nonce', herbalMixData.nonce);
    formData.append('mix_name', mixName);
    formData.append('mix_data', JSON.stringify({
      user: {
        id: herbalMixData.user_id,
        name: herbalMixData.user_name
      },
      packaging: {
        id: selectedPackagingId,
        name: selectedPackagingName, 
        capacity: selectedCapacity,
        price: selectedPrice,
        price_point: selectedPricePoint,
        point_earned: selectedPointEarned
      },
      ingredients: selectedIngredients.map(ing => ({
        id: ing.id,
        name: ing.name,
        weight: ing.weight,
        price: ing.price,
        price_point: ing.price_point,
        point_earned: ing.point_earned
      }))
    }));
    
    // Wysłanie danych do serwera
    fetch(herbalMixData.ajax_url, {
      method: 'POST',
      body: formData
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        // Przekierowanie do koszyka w przypadku sukcesu
        window.location.href = data.data.redirect_url || '/cart/';
      } else {
        // Obsługa błędu
        alert('Error adding to cart: ' + (data.data || 'Unknown error'));
        addToBasketBtn.textContent = 'Add to basket';
        addToBasketBtn.disabled = false;
      }
    })
    .catch(error => {
      console.error('Error:', error);
      alert('Error adding to cart. Please try again.');
      addToBasketBtn.textContent = 'Add to basket';
      addToBasketBtn.disabled = false;
    });
  });
  // Aktualizuj event listenery dla responsywności
window.addEventListener('resize', function() {
  // Zamknij wszystkie tooltopy przy zmianie rozmiaru ekranu
  closeAllTooltips();
});

// Dodaj event listener na scrollowanie strony aby zamykać tooltopy
window.addEventListener('scroll', function() {
  // Sprawdź czy tooltip jest aktywny
  if (document.querySelector('.active-tooltip')) {
    // Zamiast natychmiastowo zamykać, sprawdź czy user naprawdę scrolluje stronę
    // a nie scrolluje wewnątrz tooltipa
    if (!isScrollingInsideTooltip) {
      closeAllTooltips();
    }
  }
}, { passive: true });

// Flaga aby określić czy użytkownik scrolluje wewnątrz tooltipa
let isScrollingInsideTooltip = false;

// Ustaw flagę gdy user zaczyna scrollować wewnątrz tooltipa
document.addEventListener('touchstart', function(e) {
  const tooltip = document.querySelector('.active-tooltip');
  isScrollingInsideTooltip = tooltip && tooltip.contains(e.target);
}, { passive: true });

// Resetuj flagę po zakończeniu scrollowania
document.addEventListener('touchend', function() {
  isScrollingInsideTooltip = false;
}, { passive: true });
});