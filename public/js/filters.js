/**
 * Advanced Filtering System for Epistol
 * Handles all the advanced filtering capabilities in the right sidebar
 */

class AdvancedFilters {
    constructor() {
        // Initialize filter elements
        this.keywordInput = document.getElementById('keyword-search');
        this.senderInput = document.getElementById('sender-filter');
        this.recipientInput = document.getElementById('recipient-filter');
        this.hasAttachmentsCheckbox = document.getElementById('filter-has-attachments');
        this.noAttachmentsCheckbox = document.getElementById('filter-no-attachments');
        this.startDateInput = document.getElementById('date-from');
        this.endDateInput = document.getElementById('date-to');
        
        // File type checkboxes
        this.fileTypeCheckboxes = [
            document.getElementById('filter-pdf'),
            document.getElementById('filter-doc'),
            document.getElementById('filter-image'),
            document.getElementById('filter-video'),
            document.getElementById('filter-audio'),
            document.getElementById('filter-archive')
        ].filter(Boolean); // Remove null elements
        
        // Size checkboxes
        this.sizeCheckboxes = [
            document.getElementById('filter-small'),
            document.getElementById('filter-medium'),
            document.getElementById('filter-large')
        ].filter(Boolean);
        
        // Quick date buttons
        this.quickDateButtons = Array.from(document.querySelectorAll('.quick-date-btn'));
        
        // Action buttons
        this.applyFiltersBtn = document.getElementById('apply-filters-btn');
        this.clearAllBtn = document.getElementById('clear-filters-btn');
        this.savePresetBtn = document.getElementById('save-filter-preset-btn');
        this.loadPresetBtn = document.getElementById('load-preset-btn');
        this.deletePresetBtn = document.getElementById('delete-preset-btn');
        
        // Initialize event listeners only if elements exist
        if (this.keywordInput || this.senderInput || this.recipientInput) {
            this.initializeEventListeners();
        }
    }

    initializeEventListeners() {
        // Keyword search
        if (this.keywordInput) {
            this.keywordInput.addEventListener('input', this.debounceApplyFilters.bind(this));
        }
        
        // Sender and recipient filters with autocomplete
        if (this.senderInput) {
            this.senderInput.addEventListener('input', this.handleSenderFilter.bind(this));
            this.initializeAutocomplete(this.senderInput, 'sender');
        }
        
        if (this.recipientInput) {
            this.recipientInput.addEventListener('input', this.handleRecipientFilter.bind(this));
            this.initializeAutocomplete(this.recipientInput, 'recipient');
        }
        
        // Attachment filters
        if (this.hasAttachmentsCheckbox) {
            this.hasAttachmentsCheckbox.addEventListener('change', this.debounceApplyFilters.bind(this));
        }
        
        if (this.noAttachmentsCheckbox) {
            this.noAttachmentsCheckbox.addEventListener('change', this.debounceApplyFilters.bind(this));
        }
        
        // File type filters
        this.fileTypeCheckboxes.forEach(checkbox => {
            if (checkbox) {
                checkbox.addEventListener('change', this.debounceApplyFilters.bind(this));
            }
        });
        
        // Date range filters
        if (this.startDateInput) {
            this.startDateInput.addEventListener('change', this.debounceApplyFilters.bind(this));
        }
        
        if (this.endDateInput) {
            this.endDateInput.addEventListener('change', this.debounceApplyFilters.bind(this));
        }
        
        // Quick date buttons
        this.quickDateButtons.forEach(button => {
            if (button) {
                button.addEventListener('click', this.handleQuickDate.bind(this));
            }
        });
        
        // Size filters
        this.sizeCheckboxes.forEach(checkbox => {
            if (checkbox) {
                checkbox.addEventListener('change', this.debounceApplyFilters.bind(this));
            }
        });
        
        // Action buttons
        if (this.applyFiltersBtn) {
            this.applyFiltersBtn.addEventListener('click', this.applyFilters.bind(this));
        }
        
        if (this.clearAllBtn) {
            this.clearAllBtn.addEventListener('click', this.clearAllFilters.bind(this));
        }
        
        if (this.savePresetBtn) {
            this.savePresetBtn.addEventListener('click', this.savePreset.bind(this));
        }
        
        if (this.loadPresetBtn) {
            this.loadPresetBtn.addEventListener('click', this.loadPreset.bind(this));
        }
        
        if (this.deletePresetBtn) {
            this.deletePresetBtn.addEventListener('click', this.deletePreset.bind(this));
        }
        
        // Load presets on initialization
        this.loadPresets();
    }

    // Initialize autocomplete for sender/recipient fields
    initializeAutocomplete(inputElement, type) {
        let suggestions = [];
        let currentFocus = -1;
        
        // Create suggestions container
        const suggestionsContainer = document.createElement('div');
        suggestionsContainer.className = 'autocomplete-suggestions';
        inputElement.parentNode.insertBefore(suggestionsContainer, inputElement.nextSibling);
        
        // Load user suggestions
        this.loadUserSuggestions(type).then(users => {
            suggestions = users;
        });
        
        inputElement.addEventListener('input', async (e) => {
            const value = e.target.value.toLowerCase();
            
            if (value.length < 2) {
                suggestionsContainer.style.display = 'none';
                return;
            }
            
            // Filter suggestions
            const filteredSuggestions = suggestions.filter(user => 
                user.name.toLowerCase().includes(value) || 
                user.email.toLowerCase().includes(value)
            );
            
            if (filteredSuggestions.length === 0) {
                suggestionsContainer.style.display = 'none';
                return;
            }
            
            // Display suggestions
            suggestionsContainer.innerHTML = filteredSuggestions.map((user, index) => 
                `<div class="suggestion-item" data-index="${index}">
                    <div class="suggestion-name">${user.name}</div>
                    <div class="suggestion-email">${user.email}</div>
                </div>`
            ).join('');
            
            suggestionsContainer.style.display = 'block';
            currentFocus = -1;
        });
        
        // Handle keyboard navigation
        inputElement.addEventListener('keydown', (e) => {
            const items = suggestionsContainer.querySelectorAll('.suggestion-item');
            
            if (e.key === 'ArrowDown') {
                currentFocus++;
                if (currentFocus >= items.length) currentFocus = 0;
                this.setActiveSuggestion(items, currentFocus);
            } else if (e.key === 'ArrowUp') {
                currentFocus--;
                if (currentFocus < 0) currentFocus = items.length - 1;
                this.setActiveSuggestion(items, currentFocus);
            } else if (e.key === 'Enter') {
                e.preventDefault();
                if (currentFocus > -1 && items[currentFocus]) {
                    this.selectSuggestion(inputElement, items[currentFocus], suggestions);
                }
            } else if (e.key === 'Escape') {
                suggestionsContainer.style.display = 'none';
            }
        });
        
        // Handle mouse clicks on suggestions
        suggestionsContainer.addEventListener('click', (e) => {
            const item = e.target.closest('.suggestion-item');
            if (item) {
                this.selectSuggestion(inputElement, item, suggestions);
            }
        });
        
        // Hide suggestions when clicking outside
        document.addEventListener('click', (e) => {
            if (!inputElement.contains(e.target) && !suggestionsContainer.contains(e.target)) {
                suggestionsContainer.style.display = 'none';
            }
        });
    }
    
    setActiveSuggestion(items, index) {
        items.forEach(item => item.classList.remove('active'));
        if (items[index]) {
            items[index].classList.add('active');
        }
    }
    
    selectSuggestion(inputElement, item, suggestions) {
        const index = parseInt(item.dataset.index);
        const user = suggestions[index];
        inputElement.value = user.name;
        inputElement.dataset.selectedEmail = user.email;
        
        // Hide suggestions
        const suggestionsContainer = inputElement.parentNode.querySelector('.autocomplete-suggestions');
        suggestionsContainer.style.display = 'none';
        
        // Trigger filter update
        this.debounceApplyFilters();
    }
    
    async loadUserSuggestions(type) {
        try {
            const response = await fetch(`/api/v1/get_users.php?type=${type}`);
            const data = await response.json();
            
            if (data.status === 'success') {
                return data.data;
            } else {
                console.error('Error loading user suggestions:', data.message);
                return [];
            }
        } catch (error) {
            console.error('Error loading user suggestions:', error);
            return [];
        }
    }

    handleKeywordSearch(event) {
        const keyword = event.target.value.trim();
        if (keyword) {
            this.currentFilters.keyword = keyword;
        } else {
            delete this.currentFilters.keyword;
        }
        this.debounceApplyFilters();
    }

    handleSenderFilter(event) {
        const sender = event.target.value.trim();
        if (sender) {
            this.currentFilters.sender = sender;
        } else {
            delete this.currentFilters.sender;
        }
        this.debounceApplyFilters();
    }

    handleRecipientFilter(event) {
        const recipient = event.target.value.trim();
        if (recipient) {
            this.currentFilters.recipient = recipient;
        } else {
            delete this.currentFilters.recipient;
        }
        this.debounceApplyFilters();
    }

    handleQuickDate(event) {
        const days = parseInt(event.target.dataset.days);
        const toDate = new Date();
        const fromDate = new Date();
        fromDate.setDate(fromDate.getDate() - days);

        // Update date inputs
        document.getElementById('date-from').value = fromDate.toISOString().split('T')[0];
        document.getElementById('date-to').value = toDate.toISOString().split('T')[0];

        // Update active state
        document.querySelectorAll('.quick-date-btn').forEach(btn => btn.classList.remove('active'));
        event.target.classList.add('active');

        this.currentFilters.dateFrom = fromDate.toISOString().split('T')[0];
        this.currentFilters.dateTo = toDate.toISOString().split('T')[0];
        this.applyFilters();
    }

    updateFilters() {
        // Search options
        this.currentFilters.searchSubject = document.getElementById('search-subject')?.checked || false;
        this.currentFilters.searchBody = document.getElementById('search-body')?.checked || false;
        this.currentFilters.searchSender = document.getElementById('search-sender')?.checked || false;

        // People filters
        this.currentFilters.sentByMe = document.getElementById('filter-sent-by-me')?.checked || false;
        this.currentFilters.receivedByMe = document.getElementById('filter-received-by-me')?.checked || false;

        // Attachment filters
        this.currentFilters.hasAttachments = document.getElementById('filter-has-attachments')?.checked || false;
        this.currentFilters.noAttachments = document.getElementById('filter-no-attachments')?.checked || false;
        
        const fileTypes = [];
        if (document.getElementById('filter-pdf')?.checked) fileTypes.push('pdf');
        if (document.getElementById('filter-doc')?.checked) fileTypes.push('doc');
        if (document.getElementById('filter-image')?.checked) fileTypes.push('image');
        if (document.getElementById('filter-video')?.checked) fileTypes.push('video');
        if (document.getElementById('filter-audio')?.checked) fileTypes.push('audio');
        if (document.getElementById('filter-archive')?.checked) fileTypes.push('archive');
        
        if (fileTypes.length > 0) {
            this.currentFilters.fileTypes = fileTypes;
        } else {
            delete this.currentFilters.fileTypes;
        }

        // Date filters
        const dateFrom = document.getElementById('date-from')?.value;
        const dateTo = document.getElementById('date-to')?.value;
        
        if (dateFrom) this.currentFilters.dateFrom = dateFrom;
        else delete this.currentFilters.dateFrom;
        
        if (dateTo) this.currentFilters.dateTo = dateTo;
        else delete this.currentFilters.dateTo;

        // Size filters
        const sizes = [];
        if (document.getElementById('filter-small')?.checked) sizes.push('small');
        if (document.getElementById('filter-medium')?.checked) sizes.push('medium');
        if (document.getElementById('filter-large')?.checked) sizes.push('large');
        
        if (sizes.length > 0) {
            this.currentFilters.sizes = sizes;
        } else {
            delete this.currentFilters.sizes;
        }
    }

    debounceApplyFilters() {
        clearTimeout(this.debounceTimer);
        this.debounceTimer = setTimeout(() => {
            this.applyFilters();
        }, 300);
    }

    applyFilters() {
        this.updateFilters();
        
        // Convert filters to API parameters
        const apiParams = this.convertFiltersToApiParams();
        
        // Apply filters to the feed
        if (typeof loadFeed === 'function') {
            loadFeed(apiParams);
        } else {
            console.warn('loadFeed function not available');
        }
    }

    convertFiltersToApiParams() {
        const params = {};

        // Keyword search
        if (this.currentFilters.keyword) {
            params.q = this.currentFilters.keyword;
        }

        // Date range
        if (this.currentFilters.dateFrom) {
            params.date_from = this.currentFilters.dateFrom;
        }
        if (this.currentFilters.dateTo) {
            params.date_to = this.currentFilters.dateTo;
        }

        // Sender/Recipient filters
        if (this.currentFilters.sender) {
            params.sender = this.currentFilters.sender;
        }
        if (this.currentFilters.recipient) {
            params.recipient = this.currentFilters.recipient;
        }

        // Attachment filters
        if (this.currentFilters.hasAttachments) {
            params.has_attachments = true;
        }
        if (this.currentFilters.noAttachments) {
            params.no_attachments = true;
        }
        if (this.currentFilters.fileTypes) {
            params.file_types = this.currentFilters.fileTypes.join(',');
        }

        // Size filters
        if (this.currentFilters.sizes) {
            params.sizes = this.currentFilters.sizes.join(',');
        }

        return params;
    }

    clearAllFilters() {
        // Clear all input fields
        document.getElementById('keyword-search').value = '';
        document.getElementById('sender-filter').value = '';
        document.getElementById('recipient-filter').value = '';
        document.getElementById('date-from').value = '';
        document.getElementById('date-to').value = '';

        // Uncheck all checkboxes
        document.querySelectorAll('#right-sidebar input[type="checkbox"]').forEach(checkbox => {
            checkbox.checked = false;
        });

        // Reset quick date buttons
        document.querySelectorAll('.quick-date-btn').forEach(btn => btn.classList.remove('active'));

        // Clear current filters
        this.currentFilters = {};

        // Reload feed without filters
        if (typeof loadFeed === 'function') {
            loadFeed({});
        }
    }

    savePreset() {
        const presetName = prompt('Enter a name for this filter preset:');
        if (!presetName) return;

        const preset = {
            name: presetName,
            filters: { ...this.currentFilters },
            timestamp: new Date().toISOString()
        };

        this.savedPresets[presetName] = preset;
        this.savePresets();
        this.updatePresetsDropdown();
    }

    loadPreset() {
        const presetSelect = document.getElementById('filter-presets');
        const selectedPreset = presetSelect.value;
        
        if (!selectedPreset || !this.savedPresets[selectedPreset]) return;

        const preset = this.savedPresets[selectedPreset];
        this.currentFilters = { ...preset.filters };
        this.applyPresetToUI(preset.filters);
        this.applyFilters();
    }

    deletePreset() {
        const presetSelect = document.getElementById('filter-presets');
        const selectedPreset = presetSelect.value;
        
        if (!selectedPreset) return;

        if (confirm(`Are you sure you want to delete the preset "${selectedPreset}"?`)) {
            delete this.savedPresets[selectedPreset];
            this.savePresets();
            this.updatePresetsDropdown();
        }
    }

    applyPresetToUI(filters) {
        // Apply keyword search
        if (filters.keyword) {
            document.getElementById('keyword-search').value = filters.keyword;
        }

        // Apply sender/recipient filters
        if (filters.sender) {
            document.getElementById('sender-filter').value = filters.sender;
        }
        if (filters.recipient) {
            document.getElementById('recipient-filter').value = filters.recipient;
        }

        // Apply date filters
        if (filters.dateFrom) {
            document.getElementById('date-from').value = filters.dateFrom;
        }
        if (filters.dateTo) {
            document.getElementById('date-to').value = filters.dateTo;
        }

        // Apply checkboxes
        if (filters.searchSubject) document.getElementById('search-subject').checked = true;
        if (filters.searchBody) document.getElementById('search-body').checked = true;
        if (filters.searchSender) document.getElementById('search-sender').checked = true;
        if (filters.sentByMe) document.getElementById('filter-sent-by-me').checked = true;
        if (filters.receivedByMe) document.getElementById('filter-received-by-me').checked = true;
        if (filters.hasAttachments) document.getElementById('filter-has-attachments').checked = true;
        if (filters.noAttachments) document.getElementById('filter-no-attachments').checked = true;

        // Apply file type filters
        if (filters.fileTypes) {
            filters.fileTypes.forEach(type => {
                const checkbox = document.getElementById(`filter-${type}`);
                if (checkbox) checkbox.checked = true;
            });
        }

        // Apply size filters
        if (filters.sizes) {
            filters.sizes.forEach(size => {
                const checkbox = document.getElementById(`filter-${size}`);
                if (checkbox) checkbox.checked = true;
            });
        }
    }

    updatePresetsDropdown() {
        const presetSelect = document.getElementById('filter-presets');
        const currentValue = presetSelect.value;
        
        // Clear existing options except the first one
        presetSelect.innerHTML = '<option value="">Select a preset...</option>';
        
        // Add saved presets
        Object.keys(this.savedPresets).forEach(presetName => {
            const option = document.createElement('option');
            option.value = presetName;
            option.textContent = presetName;
            presetSelect.appendChild(option);
        });

        // Restore selected value if it still exists
        if (currentValue && this.savedPresets[currentValue]) {
            presetSelect.value = currentValue;
        }
    }

    loadPresets() {
        try {
            const saved = localStorage.getItem('epistol_filter_presets');
            return saved ? JSON.parse(saved) : {};
        } catch (error) {
            console.error('Error loading filter presets:', error);
            return {};
        }
    }

    savePresets() {
        try {
            localStorage.setItem('epistol_filter_presets', JSON.stringify(this.savedPresets));
        } catch (error) {
            console.error('Error saving filter presets:', error);
        }
    }
}

// Initialize filters when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.advancedFilters = new AdvancedFilters();
}); 