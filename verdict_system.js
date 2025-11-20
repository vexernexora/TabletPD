/**
 * Enhanced Verdict System JavaScript
 * Zaawansowane funkcjonalności dla systemu wyroków
 */

class VerdictSystem {
    constructor() {
        this.selectedOffenses = [];
        this.availableOffenses = [];
        this.filteredOffenses = [];
        this.currentCitizenId = null;
        this.searchTimeout = null;
        this.isAutoSaving = false;
        this.lastSavedData = null;
        
        this.init();
    }

    init() {
        this.bindEvents();
        this.setupKeyboardShortcuts();
        this.setupAutoSave();
        this.loadSettings();
    }

    bindEvents() {
        // Search input with debounce
        const searchInput = document.getElementById('verdictSearch');
        if (searchInput) {
            searchInput.addEventListener('input', (e) => {
                clearTimeout(this.searchTimeout);
                this.searchTimeout = setTimeout(() => {
                    this.filterOffenses();
                    this.highlightSearchResults(e.target.value);
                }, 300);
            });
        }

        // Category buttons
        document.querySelectorAll('.category-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                this.selectCategory(btn.dataset.category);
                this.saveUserPreference('lastCategory', btn.dataset.category);
            });
        });

        // Form validation
        const locationInput = document.getElementById('verdictLocation');
        if (locationInput) {
            locationInput.addEventListener('input', () => {
                this.validateForm();
                this.updateSaveButton();
            });
        }

        // Modal close events
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.isModalOpen('verdictModal')) {
                this.closeVerdictModal();
            }
        });

        // Auto-resize textarea
        const notesTextarea = document.getElementById('verdictNotes');
        if (notesTextarea) {
            notesTextarea.addEventListener('input', this.autoResizeTextarea);
        }
    }

    setupKeyboardShortcuts() {
        document.addEventListener('keydown', (e) => {
            // Ctrl/Cmd + S to save
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                e.preventDefault();
                if (this.isModalOpen('verdictModal') && this.canSave()) {
                    this.saveVerdict();
                }
            }

            // Ctrl/Cmd + Enter to save quickly
            if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                e.preventDefault();
                if (this.isModalOpen('verdictModal') && this.canSave()) {
                    this.saveVerdict();
                }
            }

            // Number keys 1-5 for quick category selection
            if (e.key >= '1' && e.key <= '5' && !e.ctrlKey && !e.metaKey && !e.altKey) {
                const categoryBtns = document.querySelectorAll('.category-btn');
                const index = parseInt(e.key) - 1;
                if (categoryBtns[index] && this.isModalOpen('verdictModal')) {
                    e.preventDefault();
                    categoryBtns[index].click();
                }
            }
        });
    }

    setupAutoSave() {
        // Auto-save draft every 30 seconds
        setInterval(() => {
            if (this.isModalOpen('verdictModal') && this.hasUnsavedChanges()) {
                this.saveDraft();
            }
        }, 30000);

        // Save draft when leaving page
        window.addEventListener('beforeunload', (e) => {
            if (this.hasUnsavedChanges()) {
                this.saveDraft();
                e.preventDefault();
                e.returnValue = 'Masz niezapisane zmiany. Czy na pewno chcesz opuścić stronę?';
            }
        });
    }

    openVerdictModal(citizenId) {
        this.currentCitizenId = citizenId;
        document.getElementById('verdictModal').classList.add('show');
        document.body.style.overflow = 'hidden';
        
        this.loadOffensesForVerdict();
        this.resetForm();
        this.loadDraft();
        
        // Focus search input
        setTimeout(() => {
            document.getElementById('verdictSearch')?.focus();
        }, 100);

        // Analytics
        this.trackEvent('verdict_modal_opened', { citizenId });
    }

    closeVerdictModal() {
        if (this.hasUnsavedChanges()) {
            if (!confirm('Masz niezapisane zmiany. Czy na pewno chcesz zamknąć okno?')) {
                return;
            }
        }

        document.getElementById('verdictModal').classList.remove('show');
        document.body.style.overflow = '';
        
        this.clearDraft();
        this.resetForm();
        
        this.trackEvent('verdict_modal_closed');
    }

    async loadOffensesForVerdict() {
        try {
            this.showLoading(true);
            
            const response = await fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_offenses_for_verdict'
            });

            const data = await response.json();
            
            if (data.success) {
                this.availableOffenses = data.offenses;
                this.filteredOffenses = [...this.availableOffenses];
                this.renderOffenses();
                this.restoreLastCategory();
            } else {
                this.showError('Błąd ładowania wykroczeń: ' + data.message);
            }
        } catch (error) {
            console.error('Error loading offenses:', error);
            this.showError('Wystąpił błąd podczas ładowania wykroczeń');
        } finally {
            this.showLoading(false);
        }
    }

    filterOffenses() {
        const searchTerm = document.getElementById('verdictSearch').value.toLowerCase();
        const activeCategory = document.querySelector('.category-btn.active').dataset.category;
        
        this.filteredOffenses = this.availableOffenses.filter(offense => {
            const matchesSearch = offense.nazwa.toLowerCase().includes(searchTerm) ||
                                offense.opis?.toLowerCase().includes(searchTerm) ||
                                offense.kodeks_artykul?.toLowerCase().includes(searchTerm);
            
            const matchesCategory = activeCategory === 'all' || 
                                  offense.kategoria?.toLowerCase() === activeCategory;
            
            return matchesSearch && matchesCategory;
        });
        
        this.renderOffenses();
        this.trackEvent('offenses_filtered', { 
            searchTerm, 
            category: activeCategory, 
            resultsCount: this.filteredOffenses.length 
        });
    }

    renderOffenses() {
        const grid = document.getElementById('offensesGrid');
        
        if (this.filteredOffenses.length === 0) {
            grid.innerHTML = `
                <div class="no-results">
                    <div class="no-results-icon">🔍</div>
                    <div class="no-results-text">Nie znaleziono wykroczeń</div>
                    <div class="no-results-hint">Spróbuj użyć innych słów kluczowych</div>
                </div>
            `;
            return;
        }
        
        grid.innerHTML = this.filteredOffenses.map(offense => `
            <div class="offense-card ${this.isOffenseSelected(offense.id) ? 'selected' : ''}" 
                 onclick="verdictSystem.toggleOffense(${offense.id})"
                 data-offense-id="${offense.id}">
                <div class="offense-category">${this.getCategoryDisplayName(offense.kategoria)}</div>
                <div class="offense-name">${offense.nazwa}</div>
                <div class="offense-amount">${parseFloat(offense.kwota).toFixed(2)}$</div>
                ${offense.punkty_karne ? `<div class="offense-points">${offense.punkty_karne} pkt.</div>` : ''}
                ${offense.kodeks_artykul ? `<div class="offense-article">${offense.kodeks_artykul}</div>` : ''}
                <div class="offense-actions">
                    <button class="quick-add-btn" onclick="event.stopPropagation(); verdictSystem.quickAddOffense(${offense.id})" title="Szybkie dodanie">+</button>
                </div>
            </div>
        `).join('');
    }

    toggleOffense(offenseId) {
        const offense = this.availableOffenses.find(o => o.id == offenseId);
        if (!offense) return;
        
        const existingIndex = this.selectedOffenses.findIndex(s => s.id == offenseId);
        
        if (existingIndex >= 0) {
            this.selectedOffenses[existingIndex].quantity++;
        } else {
            this.selectedOffenses.push({
                id: offense.id,
                nazwa: offense.nazwa,
                kwota: parseFloat(offense.kwota),
                quantity: 1,
                punkty_karne: offense.punkty_karne || 0
            });
        }
        
        this.updateSelectedItems();
        this.updateSaveButton();
        this.updateOffenseCardState(offenseId);
        
        // Haptic feedback on mobile
        if (navigator.vibrate) {
            navigator.vibrate(50);
        }

        this.trackEvent('offense_selected', { offenseId, quantity: this.getOffenseQuantity(offenseId) });
    }

    quickAddOffense(offenseId) {
        this.toggleOffense(offenseId);
        this.showQuickFeedback(`Dodano wykroczenie`);
    }

    updateSelectedItems() {
        const container = document.getElementById('selectedItems');
        
        if (this.selectedOffenses.length === 0) {
            container.innerHTML = `
                <div class="no-items">
                    <div class="no-items-icon">📋</div>
                    <div class="no-items-text">Nie wybrano wykroczeń</div>
                    <div class="no-items-hint">Kliknij na wykroczenie aby je dodać</div>
                </div>
            `;
            this.updateTotalAmount();
            return;
        }
        
        container.innerHTML = this.selectedOffenses.map((item, index) => `
            <div class="selected-item" data-item-id="${item.id}">
                <div class="selected-item-info">
                    <div class="selected-item-name">${item.nazwa}</div>
                    <div class="selected-item-details">
                        <span class="selected-item-amount">${item.kwota.toFixed(2)}$ × ${item.quantity} = ${(item.kwota * item.quantity).toFixed(2)}$</span>
                        ${item.punkty_karne ? `<span class="selected-item-points">${item.punkty_karne * item.quantity} pkt.</span>` : ''}
                    </div>
                </div>
                <div class="selected-item-controls">
                    <div class="quantity-control">
                        <button class="quantity-btn" onclick="verdictSystem.changeQuantity(${index}, -1)" ${item.quantity <= 1 ? 'disabled' : ''}>−</button>
                        <input type="number" class="quantity-input" value="${item.quantity}" 
                               onchange="verdictSystem.setQuantity(${index}, this.value)" 
                               min="1" max="99">
                        <button class="quantity-btn" onclick="verdictSystem.changeQuantity(${index}, 1)">+</button>
                    </div>
                    <button class="remove-item" onclick="verdictSystem.removeOffense(${index})" title="Usuń">×</button>
                </div>
            </div>
        `).join('');
        
        this.updateTotalAmount();
        this.updateOffenseStates();
    }

    changeQuantity(index, delta) {
        if (this.selectedOffenses[index]) {
            const newQuantity = Math.max(1, this.selectedOffenses[index].quantity + delta);
            this.selectedOffenses[index].quantity = newQuantity;
            this.updateSelectedItems();
            this.updateSaveButton();
            
            this.trackEvent('quantity_changed', { 
                offenseId: this.selectedOffenses[index].id, 
                newQuantity,
                delta 
            });
        }
    }

    setQuantity(index, value) {
        const quantity = Math.max(1, Math.min(99, parseInt(value) || 1));
        if (this.selectedOffenses[index]) {
            this.selectedOffenses[index].quantity = quantity;
            this.updateSelectedItems();
            this.updateSaveButton();
        }
    }

    removeOffense(index) {
        if (this.selectedOffenses[index]) {
            const removedOffense = this.selectedOffenses.splice(index, 1)[0];
            this.updateSelectedItems();
            this.updateSaveButton();
            this.updateOffenseCardState(removedOffense.id);
            
            this.showQuickFeedback(`Usunięto wykroczenie`);
            this.trackEvent('offense_removed', { offenseId: removedOffense.id });
        }
    }

    updateTotalAmount() {
        const total = this.selectedOffenses.reduce((sum, item) => sum + (item.kwota * item.quantity), 0);
        const totalPoints = this.selectedOffenses.reduce((sum, item) => sum + ((item.punkty_karne || 0) * item.quantity), 0);
        
        const amountElement = document.getElementById('totalAmount');
        if (amountElement) {
            const oldAmount = parseFloat(amountElement.dataset.amount || 0);
            amountElement.textContent = total.toFixed(2) + '$';
            amountElement.dataset.amount = total;
            
            // Add animation class for amount changes
            if (oldAmount !== total) {
                amountElement.classList.add('updated');
                setTimeout(() => amountElement.classList.remove('updated'), 500);
            }
        }

        // Update points display if exists
        const pointsElement = document.getElementById('totalPoints');
        if (pointsElement) {
            pointsElement.textContent = totalPoints > 0 ? `${totalPoints} pkt.` : '';
        }
    }

    async saveVerdict() {
        if (!this.canSave()) {
            this.showError('Nie można zapisać - sprawdź czy wszystkie pola są wypełnione');
            return;
        }

        try {
            this.setSaveButtonLoading(true);
            
            const formData = new FormData();
            formData.append('action', 'save_verdict');
            formData.append('citizen_id', this.currentCitizenId);
            formData.append('selected_offenses', JSON.stringify(this.selectedOffenses));
            formData.append('officer', document.getElementById('verdictOfficer').value);
            formData.append('location', document.getElementById('verdictLocation').value.trim());
            formData.append('notes', document.getElementById('verdictNotes').value.trim());
            
            const response = await fetch('', { 
                method: 'POST', 
                body: formData 
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.showSuccess(data.message);
                this.clearDraft();
                this.closeVerdictModal();
                
                // Refresh citizen details if function exists
                if (typeof showCitizenDetails === 'function') {
                    setTimeout(() => showCitizenDetails(this.currentCitizenId), 500);
                }
                
                this.trackEvent('verdict_saved', {
                    citizenId: this.currentCitizenId,
                    offensesCount: this.selectedOffenses.length,
                    totalAmount: this.getTotalAmount()
                });
            } else {
                this.showError('Błąd: ' + (data.message || 'Nieznany błąd'));
            }
        } catch (error) {
            console.error('Error saving verdict:', error);
            this.showError('Wystąpił błąd podczas zapisywania wyroku');
        } finally {
            this.setSaveButtonLoading(false);
        }
    }

    // Helper methods
    isOffenseSelected(offenseId) {
        return this.selectedOffenses.some(s => s.id == offenseId);
    }

    getOffenseQuantity(offenseId) {
        const offense = this.selectedOffenses.find(s => s.id == offenseId);
        return offense ? offense.quantity : 0;
    }

    updateOffenseCardState(offenseId) {
        const card = document.querySelector(`[data-offense-id="${offenseId}"]`);
        if (card) {
            const isSelected = this.isOffenseSelected(offenseId);
            card.classList.toggle('selected', isSelected);
        }
    }

    updateOffenseStates() {
        this.filteredOffenses.forEach(offense => {
            this.updateOffenseCardState(offense.id);
        });
    }

    canSave() {
        return this.selectedOffenses.length > 0 && 
               document.getElementById('verdictLocation').value.trim() !== '';
    }

    updateSaveButton() {
        const btn = document.getElementById('saveVerdictBtn');
        if (btn) {
            btn.disabled = !this.canSave();
        }
    }

    setSaveButtonLoading(loading) {
        const btn = document.getElementById('saveVerdictBtn');
        if (btn) {
            btn.disabled = loading;
            btn.textContent = loading ? 'Zapisywanie...' : 'Wystaw Wyrok';
        }
    }

    validateForm() {
        const location = document.getElementById('verdictLocation').value.trim();
        const locationGroup = document.getElementById('verdictLocation').closest('.form-group');
        
        if (location === '') {
            locationGroup.classList.add('error');
        } else {
            locationGroup.classList.remove('error');
        }
    }

    resetForm() {
        this.selectedOffenses = [];
        this.updateSelectedItems();
        document.getElementById('verdictLocation').value = '';
        document.getElementById('verdictNotes').value = '';
        document.getElementById('verdictSearch').value = '';
        this.updateSaveButton();
        
        // Reset category to default
        document.querySelectorAll('.category-btn').forEach(btn => btn.classList.remove('active'));
        document.querySelector('.category-btn[data-category="all"]')?.classList.add('active');
    }

    showLoading(show) {
        const grid = document.getElementById('offensesGrid');
        if (show) {
            grid.innerHTML = '<div class="loading-offenses">Ładowanie wykroczeń...</div>';
        }
    }

    showError(message) {
        // Create or update error message
        let errorDiv = document.getElementById('errorMessage');
        if (!errorDiv) {
            errorDiv = document.createElement('div');
            errorDiv.id = 'errorMessage';
            errorDiv.className = 'error-message';
            document.body.appendChild(errorDiv);
        }
        
        errorDiv.textContent = message;
        errorDiv.classList.add('show');
        
        setTimeout(() => {
            errorDiv.classList.remove('show');
        }, 5000);
    }

    showSuccess(message) {
        const successDiv = document.getElementById('successMessage');
        if (successDiv) {
            successDiv.textContent = message;
            successDiv.classList.add('show');
            setTimeout(() => {
                successDiv.classList.remove('show');
            }, 4000);
        }
    }

    showQuickFeedback(message) {
        // Small toast notification
        const toast = document.createElement('div');
        toast.className = 'quick-toast';
        toast.textContent = message;
        document.body.appendChild(toast);
        
        setTimeout(() => toast.classList.add('show'), 10);
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => document.body.removeChild(toast), 300);
        }, 2000);
    }

    // Utility methods
    selectCategory(category) {
        document.querySelectorAll('.category-btn').forEach(btn => btn.classList.remove('active'));
        document.querySelector(`[data-category="${category}"]`)?.classList.add('active');
        this.filterOffenses();
    }

    getCategoryDisplayName(category) {
        const names = {
            'traffic': 'Drogowe',
            'violent': 'Przemoc',
            'property': 'Mienie',
            'other': 'Inne'
        };
        return names[category] || 'Inne';
    }

    getTotalAmount() {
        return this.selectedOffenses.reduce((sum, item) => sum + (item.kwota * item.quantity), 0);
    }

    isModalOpen(modalId) {
        return document.getElementById(modalId)?.classList.contains('show') || false;
    }

    autoResizeTextarea(e) {
        e.target.style.height = 'auto';
        e.target.style.height = e.target.scrollHeight + 'px';
    }

    highlightSearchResults(searchTerm) {
        if (!searchTerm) return;
        
        // Add search highlighting to offense cards
        document.querySelectorAll('.offense-name').forEach(element => {
            const text = element.textContent;
            const highlightedText = text.replace(
                new RegExp(`(${searchTerm})`, 'gi'),
                '<mark>$1</mark>'
            );
            element.innerHTML = highlightedText;
        });
    }

    // Draft functionality
    saveDraft() {
        if (!this.currentCitizenId) return;
        
        const draftData = {
            citizenId: this.currentCitizenId,
            selectedOffenses: this.selectedOffenses,
            location: document.getElementById('verdictLocation').value,
            notes: document.getElementById('verdictNotes').value,
            timestamp: Date.now()
        };
        
        localStorage.setItem('verdictDraft', JSON.stringify(draftData));
        this.isAutoSaving = true;
        
        // Show auto-save indicator
        this.showAutoSaveIndicator();
    }

    loadDraft() {
        const draftData = JSON.parse(localStorage.getItem('verdictDraft') || 'null');
        
        if (draftData && draftData.citizenId == this.currentCitizenId) {
            // Check if draft is not too old (24 hours)
            if (Date.now() - draftData.timestamp < 24 * 60 * 60 * 1000) {
                if (confirm('Znaleziono zapisaną wersję roboczą. Czy chcesz ją przywrócić?')) {
                    this.selectedOffenses = draftData.selectedOffenses || [];
                    document.getElementById('verdictLocation').value = draftData.location || '';
                    document.getElementById('verdictNotes').value = draftData.notes || '';
                    
                    this.updateSelectedItems();
                    this.updateSaveButton();
                }
            }
        }
    }

    clearDraft() {
        localStorage.removeItem('verdictDraft');
    }

    hasUnsavedChanges() {
        if (!this.currentCitizenId) return false;
        
        const currentData = {
            selectedOffenses: this.selectedOffenses,
            location: document.getElementById('verdictLocation')?.value || '',
            notes: document.getElementById('verdictNotes')?.value || ''
        };
        
        return JSON.stringify(currentData) !== JSON.stringify(this.lastSavedData || {});
    }

    showAutoSaveIndicator() {
        let indicator = document.getElementById('autoSaveIndicator');
        if (!indicator) {
            indicator = document.createElement('div');
            indicator.id = 'autoSaveIndicator';
            indicator.className = 'auto-save-indicator';
            indicator.textContent = 'Automatycznie zapisano';
            document.body.appendChild(indicator);
        }
        
        indicator.classList.add('show');
        setTimeout(() => {
            indicator.classList.remove('show');
        }, 2000);
    }

    // User preferences
    saveUserPreference(key, value) {
        const prefs = JSON.parse(localStorage.getItem('verdictUserPrefs') || '{}');
        prefs[key] = value;
        localStorage.setItem('verdictUserPrefs', JSON.stringify(prefs));
    }

    getUserPreference(key, defaultValue = null) {
        const prefs = JSON.parse(localStorage.getItem('verdictUserPrefs') || '{}');
        return prefs[key] !== undefined ? prefs[key] : defaultValue;
    }

    loadSettings() {
        // Restore last used category
        this.restoreLastCategory();
    }

    restoreLastCategory() {
        const lastCategory = this.getUserPreference('lastCategory', 'all');
        this.selectCategory(lastCategory);
    }

    // Analytics
    trackEvent(eventName, data = {}) {
        if (typeof gtag !== 'undefined') {
            gtag('event', eventName, data);
        }
        
        console.log('Event tracked:', eventName, data);
    }
}

// Initialize the verdict system when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    window.verdictSystem = new VerdictSystem();
});

// Global functions for backward compatibility
function openVerdictModal() {
    if (window.verdictSystem && currentCitizenId) {
        window.verdictSystem.openVerdictModal(currentCitizenId);
    }
}

function closeVerdictModal() {
    if (window.verdictSystem) {
        window.verdictSystem.closeVerdictModal();
    }
}

function saveVerdict() {
    if (window.verdictSystem) {
        window.verdictSystem.saveVerdict();
    }
}

// Export for module usage
if (typeof module !== 'undefined' && module.exports) {
    module.exports = VerdictSystem;
}
