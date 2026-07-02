/**
 * Floorit AI Modal SDK
 * Modal-based embeddable widget for AI flooring visualization
 * All CSS classes use 'epoxy-' prefix to prevent collisions
 */

(function(window) {
    'use strict';

    class EpoxyModal {
        constructor() {
            this.instances = new Map();
            this.currentInstance = null;
            this.apiBaseUrl = this.getApiBaseUrl();
            this.injectStyles();
        }

        /**
         * ...
         */
        

        reset() {
            this.currentStep = 1;
            this.uploadedImage = null;
            this.selectedTexture = null;
            this.customPrompt = '';
            this.generationId = null;
            this.renderStep();
        }
    }

    // Initialize SDK
    const epoxyModal = new EpoxyModal();

    // Expose to global scope
    window.EpoxyModal = {
        init: (config) => epoxyModal.init(config)
    };

})(window);
