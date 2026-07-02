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
         * Initialize a new modal instance
         */
        init(config) {
            // Validate configuration
            if (!config.apiKey) {
                console.error('EpoxyModal: API key is required!!');
                return;
            }

            if (!config.embedId) {
                console.error('EpoxyModal: Embed ID is required');
                return;
            }

            // Validate API key before showing modal
            this.validateApiKey(config).then(isValid => {
                if (!isValid) {
                    this.showError('Invalid or inactive API key');
                    return;
                }

                // Create modal instance
                const instance = new EpoxyModalInstance(config, this);
                this.instances.set(config.embedId, instance);
                this.currentInstance = instance;

                // Show modal
                instance.show();
            }).catch(error => {
                console.error('EpoxyModal: API key validation failed', error);
                this.showError('Failed to validate API key');
            });
        }

        /**
         * Validate API key with server
         */
         
        async validateApiKey(config) {
            try {
                const response = await fetch(`${this.apiBaseUrl}/embed/validate-key`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': `Bearer ${config.apiKey}`
                    },
                    body: JSON.stringify({ api_key: config.apiKey })
                });

                if (!response.ok) return false;

                const data = await response.json();
                return data.data?.valid === true;
            } catch (error) {
                console.error('API key validation error:', error);
                return false;
            }
        }

        /**
         * Show error message
         */
        showError(message) {
            // Create error toast
            const toast = document.createElement('div');
            toast.className = 'epoxy-toast epoxy-toast-error';
            toast.innerHTML = `
                <div class="epoxy-toast-body">
                    <i class="epoxy-icon epoxy-icon-error"></i>
                    ${message}
                    <button class="epoxy-toast-close" onclick="this.parentElement.parentElement.remove()">&times;</button>
                </div>
            `;
            document.body.appendChild(toast);

            // Auto-remove after 5 seconds
            setTimeout(() => {
                if (toast.parentElement) {
                    toast.remove();
                }
            }, 5000);
        }

        /**
         * Get API base URL
         */
        getApiBaseUrl() {
            // Try to determine base URL from current script
            const scripts = document.getElementsByTagName('script');
            for (let script of scripts) {
                if (script.src && script.src.includes('/sdk/floorit.js')) {
                    const url = new URL(script.src);
                    return `${url.protocol}//${url.host}/api`;
                }
            }
            // Fallback
            return window.location.origin + '/api';
        }

        /**
         * Inject modal styles with unique prefixes
         */
        injectStyles() {
            if (document.getElementById('epoxy-modal-styles')) {
                return; // Already injected
            }

            const styles = `
                /* Epoxy Modal Styles - All classes prefixed with 'epoxy-' */
                .epoxy-modal-backdrop {
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0, 0, 0, 0.5);
                    z-index: 999998;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    opacity: 0;
                    transition: opacity 0.3s ease;
                    padding: 20px;
                    overflow-y: auto;
                }

                .epoxy-modal-backdrop.epoxy-show {
                    opacity: 1;
                }

                .epoxy-modal {
                    position: fixed;
                    top: 50%;
                    left: 50%;
                    transform: translate(-50%, -50%) scale(0.9);
                    z-index: 999999;
                    background: white;
                    border-radius: 12px;
                    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
                    width: 90%;
                    max-width: 600px;
                    max-height: 90vh;
                    display: flex;
                    flex-direction: column;
                    transition: transform 0.3s ease, opacity 0.3s ease;
                    opacity: 0;
                }

                .epoxy-modal.epoxy-show {
                    transform: translate(-50%, -50%) scale(1);
                    opacity: 1;
                }

                .epoxy-modal-header {
                    padding: 1.5rem 2rem;
                    border-bottom: 1px solid #e9ecef;
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    flex-shrink: 0;
                }

                .epoxy-modal-branding {
                    flex-grow: 1;
                }

                .epoxy-modal-subtitle {
                    margin: 0;
                    font-size: 0.875rem;
                    color: #6c757d;
                    font-weight: normal;
                }

                .epoxy-modal-close {
                    background: none;
                    border: none;
                    font-size: 1.5rem;
                    color: #6c757d;
                    cursor: pointer;
                    padding: 0.25rem;
                    border-radius: 4px;
                    transition: all 0.2s;
                }

                .epoxy-modal-close:hover {
                    background: #f8f9fa;
                    color: #495057;
                }

                .epoxy-modal-body {
                    padding: 2rem;
                    overflow-y: auto;
                    flex: 1;
                    min-height: 0;
                }

                .epoxy-modal-body::-webkit-scrollbar {
                    width: 8px;
                }

                .epoxy-modal-body::-webkit-scrollbar-track {
                    background: #f8f9fa;
                }

                .epoxy-modal-body::-webkit-scrollbar-thumb {
                    background: #cbd5e0;
                    border-radius: 4px;
                }

                .epoxy-modal-body::-webkit-scrollbar-thumb:hover {
                    background: #a0aec0;
                }

                .epoxy-modal-footer {
                    padding: 1rem 2rem;
                    border-top: 1px solid #e9ecef;
                    display: flex;
                    justify-content: flex-end;
                    gap: 0.75rem;
                    flex-shrink: 0;
                }

                /* Step Navigation */
                .epoxy-steps {
                    display: flex;
                    justify-content: center;
                    margin-bottom: 2rem;
                    position: relative;
                }

                .epoxy-steps::before {
                    content: '';
                    position: absolute;
                    top: 15px;
                    left: 0;
                    right: 0;
                    height: 2px;
                    background: #e9ecef;
                    z-index: 1;
                }

                .epoxy-step {
                    background: white;
                    border: 2px solid #e9ecef;
                    border-radius: 50%;
                    width: 30px;
                    height: 30px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-weight: 600;
                    font-size: 0.875rem;
                    color: #6c757d;
                    position: relative;
                    z-index: 2;
                    transition: all 0.3s ease;
                }

                .epoxy-step.epoxy-active {
                    background: #007bff;
                    border-color: #007bff;
                    color: white;
                }

                .epoxy-step.epoxy-completed {
                    background: #28a745;
                    border-color: #28a745;
                    color: white;
                }

                /* Form Elements */
                .epoxy-form-group {
                    margin-bottom: 1.5rem;
                }

                .epoxy-form-label {
                    display: block;
                    margin-bottom: 0.5rem;
                    font-weight: 500;
                    color: #495057;
                }

                .epoxy-form-control {
                    width: 100%;
                    padding: 0.75rem;
                    border: 1px solid #ced4da;
                    border-radius: 6px;
                    font-size: 1rem;
                    transition: border-color 0.2s, box-shadow 0.2s;
                }

                .epoxy-form-control:focus {
                    outline: none;
                    border-color: #007bff;
                    box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
                }

                .epoxy-form-select {
                    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m6 8 4 4 4-4'/%3e%3c/svg%3e");
                    background-position: right 0.75rem center;
                    background-repeat: no-repeat;
                    background-size: 1rem;
                    padding-right: 2.5rem;
                }

                /* Buttons */
                .epoxy-btn {
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    padding: 0.75rem 1.5rem;
                    border: none;
                    border-radius: 6px;
                    font-size: 1rem;
                    font-weight: 500;
                    text-decoration: none;
                    cursor: pointer;
                    transition: all 0.2s ease;
                    gap: 0.5rem;
                }

                .epoxy-btn:disabled {
                    opacity: 0.6;
                    cursor: not-allowed;
                }

                .epoxy-btn-primary {
                    background: #007bff;
                    color: white;
                }

                .epoxy-btn-primary:hover:not(:disabled) {
                    background: #0056b3;
                }

                .epoxy-btn-secondary {
                    background: #6c757d;
                    color: white;
                }

                .epoxy-btn-secondary:hover:not(:disabled) {
                    background: #545b62;
                }

                .epoxy-btn-outline {
                    background: transparent;
                    border: 1px solid #dee2e6;
                    color: #495057;
                }

                .epoxy-btn-outline:hover:not(:disabled) {
                    background: #f8f9fa;
                }

                /* Upload Area */
                .epoxy-upload-area {
                    border: 2px dashed #dee2e6;
                    border-radius: 8px;
                    padding: 2rem;
                    text-align: center;
                    cursor: pointer;
                    transition: all 0.3s ease;
                    background: #f8f9fa;
                }

                .epoxy-upload-area:hover {
                    border-color: #007bff;
                    background: #e7f3ff;
                }

                .epoxy-upload-area.epoxy-dragover {
                    border-color: #007bff;
                    background: #e7f3ff;
                }

                .epoxy-upload-icon {
                    font-size: 3rem;
                    color: #6c757d;
                    margin-bottom: 1rem;
                }

                .epoxy-upload-text {
                    margin-bottom: 0.5rem;
                    font-weight: 500;
                    color: #495057;
                }

                .epoxy-upload-hint {
                    font-size: 0.875rem;
                    color: #6c757d;
                }

                /* Progress */
                .epoxy-progress {
                    margin: 2rem 0;
                }

                .epoxy-progress-bar {
                    width: 100%;
                    height: 8px;
                    background: #e9ecef;
                    border-radius: 4px;
                    overflow: hidden;
                }

                .epoxy-progress-fill {
                    height: 100%;
                    background: #007bff;
                    width: 0%;
                    transition: width 0.3s ease;
                }

                .epoxy-progress-text {
                    text-align: center;
                    margin-top: 0.5rem;
                    font-size: 0.875rem;
                    color: #6c757d;
                }

                /* Grid Layouts */
                .epoxy-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                    gap: 1rem;
                    margin: 1.5rem 0;
                }

                .epoxy-grid-item {
                    padding: 1rem;
                    border: 1px solid #e9ecef;
                    border-radius: 6px;
                    cursor: pointer;
                    transition: all 0.2s ease;
                    text-align: center;
                }

                .epoxy-grid-item:hover {
                    border-color: #007bff;
                    background: #f8f9fa;
                }

                .epoxy-grid-item.selected {
                    border-color: #007bff;
                    background: #e7f3ff;
                }
                
                /* Texture Categories */
                .epoxy-texture-category {
                    margin-bottom: 2rem;
                }

                .epoxy-category-title {
                    font-size: 1.1rem;
                    font-weight: 600;
                    color: #333;
                    margin-bottom: 1rem;
                    padding-bottom: 0.5rem;
                    border-bottom: 2px solid #e9ecef;
                }
                
                .epoxy-modal.epoxy-theme-dark .epoxy-category-title {
                    color: #ffffff;
                    border-bottom-color: #444;
                }

                .epoxy-category-grid {
                    display: grid;
                    grid-template-columns: repeat(4, 1fr);
                    gap: 0.5rem;
                }

                .epoxy-category-grid .epoxy-grid-item {
                    padding: 0.5rem;
                    font-size: 0.75rem;
                }

                /* Toast Notifications */
                .epoxy-toast {
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    z-index: 1000000;
                    min-width: 300px;
                    max-width: 500px;
                    background: white;
                    border-radius: 6px;
                    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
                    border-left: 4px solid #dc3545;
                    opacity: 0;
                    transform: translateX(100%);
                    transition: all 0.3s ease;
                }

                .epoxy-toast.epoxy-show {
                    opacity: 1;
                    transform: translateX(0);
                }

                .epoxy-toast-error {
                    border-left-color: #dc3545;
                }

                .epoxy-toast-success {
                    border-left-color: #28a745;
                }

                .epoxy-toast-body {
                    padding: 1rem;
                    display: flex;
                    align-items: center;
                    gap: 0.75rem;
                }

                .epoxy-toast-close {
                    background: none;
                    border: none;
                    font-size: 1.25rem;
                    color: #6c757d;
                    cursor: pointer;
                    padding: 0;
                    margin-left: auto;
                }

                /* Spinner */
                .epoxy-spinner {
                    width: 40px;
                    height: 40px;
                    border: 4px solid #e9ecef;
                    border-top: 4px solid #007bff;
                    border-radius: 50%;
                    animation: epoxy-spin 1s linear infinite;
                    margin: 0 auto;
                }

                @keyframes epoxy-spin {
                    0% { transform: rotate(0deg); }
                    100% { transform: rotate(360deg); }
                }

                /* Result image */
                .epoxy-result-image {
                    border-radius: 8px;
                    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
                }
                
                @keyframes flash {
                    0% { opacity: 1; }
                    50% { opacity: 0.7; background-color: rgba(255, 255, 255, 0.8); }
                    100% { opacity: 1; }
                }

                /* Dark Theme Support */
                .epoxy-modal.epoxy-theme-dark {
                    background: #1a1a1a;
                    color: #ffffff;
                }

                .epoxy-modal.epoxy-theme-dark .epoxy-modal-header {
                    border-bottom-color: #333;
                }

                .epoxy-modal.epoxy-theme-dark .epoxy-modal-footer {
                    border-top-color: #333;
                }

                .epoxy-modal.epoxy-theme-dark .epoxy-form-control {
                    background: #2d2d2d;
                    border-color: #444;
                    color: #ffffff;
                }

                .epoxy-modal.epoxy-theme-dark .epoxy-form-control:focus {
                    border-color: #007bff;
                }

                .epoxy-modal.epoxy-theme-dark .epoxy-upload-area {
                    background: #2d2d2d;
                    border-color: #444;
                }

                .epoxy-modal.epoxy-theme-dark .epoxy-upload-area:hover {
                    background: #3d3d3d;
                }

                /* Utility Classes */
                .epoxy-text-center {
                    text-align: center;
                }

                .epoxy-text-muted {
                    color: #6c757d;
                }

                .epoxy-mb-3 {
                    margin-bottom: 1rem;
                }

                .epoxy-mb-4 {
                    margin-bottom: 1.5rem;
                }

                .epoxy-mt-3 {
                    margin-top: 1rem;
                }

                .epoxy-w-100 {
                    width: 100%;
                }
            `;

            const styleSheet = document.createElement('style');
            styleSheet.id = 'epoxy-modal-styles';
            styleSheet.textContent = styles;
            document.head.appendChild(styleSheet);
        }
    }

    /**
     * Individual Modal Instance
     */
    class EpoxyModalInstance {
        constructor(config, sdk) {
            this.config = config;
            this.sdk = sdk;
            this.currentStep = 1;
            this.uploadedImage = null;
            this.selectedTexture = null;
            // this.customPrompt = '';
            this.generationId = null;
            this.modal = null;
            this.backdrop = null;
            this.userInfo = null;
        }

        /**
         * Show the modal
         */
        show() {
            // First validate and get user info
            this.validateAndGetUserInfo().then(userInfo => {
                this.userInfo = userInfo;
                this.createModal();
                this.renderStep();
                document.body.appendChild(this.backdrop);
                document.body.appendChild(this.modal);

                // Trigger animation
                setTimeout(() => {
                    this.backdrop.classList.add('epoxy-show');
                    this.modal.classList.add('epoxy-show');
                }, 10);

                // Prevent body scroll
                document.body.style.overflow = 'hidden';
            }).catch(error => {
                console.error('Failed to validate user:', error);
                this.sdk.showError('Failed to initialize modal');
            });
        }

        /**
         * Validate API key and get user info
         */
        async validateAndGetUserInfo() {
            const response = await fetch(`${this.sdk.apiBaseUrl}/embed/validate-key`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${this.config.apiKey}`
                },
                body: JSON.stringify({ api_key: this.config.apiKey })
            });

            if (!response.ok) throw new Error('API key validation failed');

            const res = await response.json();
            if (!res.data?.valid) throw new Error('Invalid or inactive embed token');

            return res.data.user || { name: 'Floorit AI User' };
        }

        /**
         * Hide the modal
         */
        hide() {
            // Stop camera if running
            this.stopCamera();
            
            this.backdrop.classList.remove('epoxy-show');
            this.modal.classList.remove('epoxy-show');

            setTimeout(() => {
                if (this.backdrop.parentElement) {
                    this.backdrop.remove();
                }
                if (this.modal.parentElement) {
                    this.modal.remove();
                }
                document.body.style.overflow = '';
            }, 300);
        }

        /**
         * Create modal HTML structure
         */
        createModal() {
            // Backdrop
            this.backdrop = document.createElement('div');
            this.backdrop.className = 'epoxy-modal-backdrop';
            this.backdrop.onclick = (e) => {
                if (e.target === this.backdrop) {
                    this.hide();
                }
            };

            // Modal
            this.modal = document.createElement('div');
            this.modal.className = `epoxy-modal ${this.config.theme === 'dark' ? 'epoxy-theme-dark' : ''}`;

            this.modal.innerHTML = `
                <div class="epoxy-modal-body">
                    <div class="epoxy-steps">
                        <div class="epoxy-step epoxy-active" data-step="1">1</div>
                        <div class="epoxy-step" data-step="2">2</div>
                        <div class="epoxy-step" data-step="3">3</div>
                    </div>
                    <div id="epoxy-modal-content"></div>
                </div>
                <div class="epoxy-modal-footer">
                    <button class="epoxy-btn epoxy-btn-outline" id="epoxy-prev-btn" onclick="this.dispatchEvent(new CustomEvent('prev'))">Back</button>
                    <button class="epoxy-btn epoxy-btn-primary" id="epoxy-next-btn" onclick="this.dispatchEvent(new CustomEvent('next'))">Continue</button>
                </div>
            `;

            // Event listeners
            this.modal.addEventListener('close', () => this.hide());
            this.modal.querySelector('#epoxy-prev-btn').addEventListener('prev', () => this.prevStep());
            this.modal.querySelector('#epoxy-next-btn').addEventListener('next', () => this.nextStep());
        }

        /**
         * Render current step
         */
        renderStep() {
            const content = this.modal.querySelector('#epoxy-modal-content');
            const prevBtn = this.modal.querySelector('#epoxy-prev-btn');
            const nextBtn = this.modal.querySelector('#epoxy-next-btn');

            // Update step indicators
            this.updateStepIndicators();

            switch (this.currentStep) {
                case 1:
                    content.innerHTML = this.renderUploadStep();
                    prevBtn.classList.add('epoxy-hidden');
                    nextBtn.textContent = 'Continue';
                    nextBtn.disabled = !this.uploadedImage;
                    break;
                case 2:
                    content.innerHTML = this.renderTextureStep();
                    prevBtn.classList.remove('epoxy-hidden');
                    nextBtn.textContent = 'Generate Design';
                    nextBtn.disabled = !this.selectedTexture;;
                    // Store reference to next button for later disabling during generation
                    this.generateBtn = nextBtn;
                    break;
                case 3:
                    // Step 3 (custom prompt) is commented out for now
                    // content.innerHTML = this.renderPromptStep();
                    // prevBtn.classList.remove('epoxy-hidden');
                    // nextBtn.textContent = 'Generate Design';
                    // nextBtn.disabled = false;
                    // // Store reference to next button for later disabling during generation
                    // this.generateBtn = nextBtn;
                    // break;
                case 4:
                    content.innerHTML = this.renderGenerationStep();
                    prevBtn.classList.add('epoxy-hidden');
                    nextBtn.classList.add('epoxy-hidden');
                    // Disable the generate button during processing
                    if (this.generateBtn) {
                        this.generateBtn.disabled = true;
                        this.generateBtn.textContent = 'Processing...';
                    }
                    this.startGeneration();
                    break;
            }
        }

        /**
         * Update step indicators
         */
        updateStepIndicators() {
            const steps = this.modal.querySelectorAll('.epoxy-step');
            steps.forEach((step, index) => {
                const stepNum = index + 1;
                step.classList.remove('epoxy-active', 'epoxy-completed');

                // Map display steps to actual steps (skipping step 3)
                let actualStep;
                if (stepNum === 1) actualStep = 1;
                else if (stepNum === 2) actualStep = 2;
                else if (stepNum === 3) actualStep = 4; // Step 3 display shows step 4

                if (actualStep === this.currentStep) {
                    step.classList.add('epoxy-active');
                } else if (actualStep < this.currentStep) {
                    step.classList.add('epoxy-completed');
                }
            });
        }

        /**
         * Go to next step
         */
        nextStep() {
            if (this.currentStep === 1) {
                this.currentStep = 2;
            } else if (this.currentStep === 2) {
                this.currentStep = 4; // Skip step 3, go directly to generation
            }
            // Can't go beyond step 4
            this.renderStep();
        }

        /**
         * Go to previous step
         */
        prevStep() {
            if (this.currentStep > 1) {
                this.currentStep--;
                this.renderStep();
            }
        }

        /**
         * Render upload step
         */
        renderUploadStep() {
            const stepHtml = `
                <div class="epoxy-text-center epoxy-mb-4">
                    <button class="epoxy-btn epoxy-btn-outline" id="epoxy-toggle-camera-btn" style="margin-bottom: 1rem;">📸 Use Camera</button>
                </div>

                <div class="epoxy-upload-area" id="epoxy-upload-area">
                    <div class="epoxy-upload-icon">📁</div>
                    <div class="epoxy-upload-text">Click to upload or drag and drop</div>
                    <div class="epoxy-upload-hint">PNG, JPG up to 10MB</div>
                    <input type="file" id="epoxy-file-input" accept="image/*" style="display: none;">
                </div>

                <div class="epoxy-form-group" id="epoxy-camera-input" style="display: none;">
                    <div id="epoxy-camera-preview" style="position: relative; background: #000; border-radius: 8px; overflow: hidden; margin-bottom: 1rem;">
                        <video id="epoxy-camera-stream" autoplay playsinline style="width: 100%; height: 300px; object-fit: cover;"></video>
                        <canvas id="epoxy-camera-canvas" style="display: none;"></canvas>
                    </div>
                    <button class="epoxy-btn epoxy-btn-primary epoxy-w-100" id="epoxy-capture-btn">📸 Capture Photo</button>
                    <div class="epoxy-text-center" style="margin-top: 1rem;">
                        <button class="epoxy-btn epoxy-btn-outline" id="epoxy-toggle-upload-btn">📁 Upload File</button>
                    </div>
                </div>
            `;

            // Set HTML and attach event listeners
            setTimeout(() => this.attachUploadEventListeners(), 10);

            return stepHtml;
        }

        /**
         * Attach upload event listeners
         */
        attachUploadEventListeners() {
            const uploadArea = this.modal.querySelector('#epoxy-upload-area');
            const fileInput = this.modal.querySelector('#epoxy-file-input');
            const toggleCameraBtn = this.modal.querySelector('#epoxy-toggle-camera-btn');
            const cameraInput = this.modal.querySelector('#epoxy-camera-input');
            const captureBtn = this.modal.querySelector('#epoxy-capture-btn');

            if (!uploadArea) return;

            // File upload events
            uploadArea.addEventListener('click', () => fileInput.click());
            uploadArea.addEventListener('dragover', this.handleDragOver.bind(this));
            uploadArea.addEventListener('drop', this.handleFileDrop.bind(this));
            fileInput.addEventListener('change', this.handleFileSelect.bind(this));

            // Camera toggle
            if (toggleCameraBtn) {
                toggleCameraBtn.addEventListener('click', async () => {
                    const isCameraVisible = cameraInput.style.display !== 'none';
                    
                    if (isCameraVisible) {
                        // Switch to upload mode
                        this.stopCamera();
                        cameraInput.style.display = 'none';
                        uploadArea.style.display = 'block';
                        toggleCameraBtn.textContent = '📸 Use Camera';
                    } else {
                        // Switch to camera mode
                        uploadArea.style.display = 'none';
                        cameraInput.style.display = 'block';
                        toggleCameraBtn.textContent = '📁 Upload File';
                        await this.startCamera();
                    }
                });
            }

            // Capture photo from camera
            if (captureBtn) {
                captureBtn.addEventListener('click', () => {
                    this.capturePhoto();
                });
            }
        }

        /**
         * Start camera stream
         */
        async startCamera() {
            try {
                const video = this.modal.querySelector('#epoxy-camera-stream');
                const stream = await navigator.mediaDevices.getUserMedia({ 
                    video: { 
                        facingMode: 'environment', // Use back camera on mobile
                        width: { ideal: 1920 },
                        height: { ideal: 1080 }
                    } 
                });
                
                video.srcObject = stream;
                this.cameraStream = stream;
            } catch (error) {
                console.error('Error accessing camera:', error);
                this.sdk.showError('Could not access camera. Please check permissions.');
                
                // Revert to upload mode
                const uploadArea = this.modal.querySelector('#epoxy-upload-area');
                const cameraInput = this.modal.querySelector('#epoxy-camera-input');
                const toggleCameraBtn = this.modal.querySelector('#epoxy-toggle-camera-btn');
                
                cameraInput.style.display = 'none';
                uploadArea.style.display = 'block';
                toggleCameraBtn.textContent = '📸 Use Camera';
            }
        }

        /**
         * Stop camera stream
         */
        stopCamera() {
            if (this.cameraStream) {
                this.cameraStream.getTracks().forEach(track => track.stop());
                this.cameraStream = null;
            }
        }

        /**
         * Capture photo from camera
         */
        capturePhoto() {
            const video = this.modal.querySelector('#epoxy-camera-stream');
            const canvas = this.modal.querySelector('#epoxy-camera-canvas');
            const context = canvas.getContext('2d');
            
            console.log('Capturing photo...');
            console.log('Video dimensions:', video.videoWidth, 'x', video.videoHeight);
            console.log('Video readyState:', video.readyState);
            
            // Check if video is ready
            if (!video.videoWidth || !video.videoHeight) {
                console.error('Video not ready for capture');
                this.sdk.showError('Camera not ready. Please wait a moment and try again.');
                return;
            }

            // Set canvas size to video dimensions
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            
            // Clear canvas first
            context.clearRect(0, 0, canvas.width, canvas.height);

            // Draw video frame to canvas
            context.drawImage(video, 0, 0, canvas.width, canvas.height);

            // Convert canvas to blob and create file
            canvas.toBlob((blob) => {
                if (!blob) {
                    console.error('Failed to create blob from canvas');
                    this.sdk.showError('Failed to capture photo. Please try again.');
                    return;
                }
                
                console.log('Blob created, size:', blob.size);
                
                const file = new File([blob], `camera-capture-${Date.now()}.jpg`, { type: 'image/jpeg' });
                console.log('Captured photo:', file.size, 'bytes');
                console.log('File created:', file.size, 'bytes');

                // Stop camera before showing preview
                this.stopCamera();
                // this.handleFile(file);
                
                // Show captured image preview temporarily
                this.showCapturedPreview(canvas);

                // Process the file after a short delay to allow preview to show
                setTimeout(() => {
                    this.handleFile(file);
                }, 500);
            }, 'image/jpeg', 0.95);
        }
        
        showCapturedPreview(canvas) {
            const cameraPreview = this.modal.querySelector('#epoxy-camera-preview');

            // Create a temporary image element to show the captured photo
            const capturedImage = document.createElement('img');
            capturedImage.src = canvas.toDataURL('image/jpeg', 0.95);
            capturedImage.style.width = '100%';
            capturedImage.style.height = '300px';
            capturedImage.style.objectFit = 'cover';
            capturedImage.style.borderRadius = '8px';

            // Replace video with captured image
            const video = cameraPreview.querySelector('video');
            if (video) {
                video.style.display = 'none';
                cameraPreview.appendChild(capturedImage);
            }

            // Add a flash effect
            cameraPreview.style.animation = 'flash 0.3s ease-out';
            setTimeout(() => {
                cameraPreview.style.animation = '';
            }, 300);
        }

        /**
         * Handle drag over
         */
        handleDragOver(e) {
            e.preventDefault();
            e.currentTarget.classList.add('epoxy-dragover');
        }

        /**
         * Handle file drop
         */
        handleFileDrop(e) {
            e.preventDefault();
            e.currentTarget.classList.remove('epoxy-dragover');

            const files = e.dataTransfer.files;
            if (files.length > 0) {
                this.handleFile(files[0]);
            }
        }

        /**
         * Handle file select
         */
        handleFileSelect(e) {
            const files = e.target.files;
            if (files.length > 0) {
                this.handleFile(files[0]);
            }
        }

        /**
         * Handle file validation and preview
         */
        handleFile(file) {
            if (!file.type.startsWith('image/')) {
                this.sdk.showError('Please select an image file');
                return;
            }

            if (file.size > 10 * 1024 * 1024) { // 10MB
                this.sdk.showError('File size must be less than 10MB');
                return;
            }

            this.uploadedImage = file;
            this.updateUploadPreview();
            this.updateNextButton();
        }

        /**
         * Update upload preview
         */
        updateUploadPreview() {
            const uploadArea = this.modal.querySelector('#epoxy-upload-area');
            if (this.uploadedImage) {
                // Show success message without image preview to avoid overloading modal
                // uploadArea.innerHTML = `
                //     <div class="epoxy-upload-icon">✓</div>
                //     <div class="epoxy-upload-text" style="color: #28a745; font-weight: 600;">Image uploaded successfully!</div>
                //     <div class="epoxy-upload-hint">${this.uploadedImage.name} (${(this.uploadedImage.size / 1024 / 1024).toFixed(2)}MB)</div>
                //     <button class="epoxy-btn epoxy-btn-outline epoxy-mt-3" onclick="this.closest('.epoxy-upload-area').dispatchEvent(new CustomEvent('change-image'))">Change Image</button>
                // `;
                
                // Create image preview
                const reader = new FileReader();
                reader.onload = (e) => {
                    uploadArea.innerHTML = `
                        <div class="epoxy-upload-preview">
                            <img src="${e.target.result}" alt="Preview" style="width: 100%; height: 200px; object-fit: cover; border-radius: 8px; margin-bottom: 1rem; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                            <div class="epoxy-upload-icon" style="color: #28a745; margin-bottom: 0.5rem;">✓</div>
                            <div class="epoxy-upload-text" style="color: #28a745; font-weight: 600; margin-bottom: 0.5rem;">Image ready!</div>
                            <div class="epoxy-upload-hint">${this.uploadedImage.name} (${(this.uploadedImage.size / 1024 / 1024).toFixed(2)}MB)</div>
                            <button class="epoxy-btn epoxy-btn-outline epoxy-mt-3" onclick="this.closest('.epoxy-upload-area').dispatchEvent(new CustomEvent('change-image'))">Change Image</button>
                        </div>
                    `;

                // // Re-attach change image listener
                // uploadArea.addEventListener('change-image', () => {
                //     this.uploadedImage = null;
                //     this.renderStep();
                // });
                
                // Re-attach change image listener
                    uploadArea.addEventListener('change-image', () => {
                        this.uploadedImage = null;
                        this.renderStep();
                    });
                };
                reader.readAsDataURL(this.uploadedImage);
            }
        }

        /**
         * Update next button state
         */
        updateNextButton() {
            const nextBtn = this.modal.querySelector('#epoxy-next-btn');
            if (nextBtn) {
                nextBtn.disabled = !this.uploadedImage;
            }
        }

        /**
         * Render texture selection step
         */
        renderTextureStep() {
            const stepHtml = `
                <div class="epoxy-grid" id="epoxy-texture-grid">
                    <div class="epoxy-text-center">
                        <div class="epoxy-spinner"></div>
                        <p class="epoxy-text-muted">Loading textures...</p>
                    </div>
                </div>
            `;

            // Load textures after rendering
            setTimeout(() => this.loadTextures(), 10);

            return stepHtml;
        }

        /**
         * Load approved textures from API
         */
        async loadTextures() {
            try {
                const url = `${this.sdk.apiBaseUrl}/embed/textures?api_key=${encodeURIComponent(this.config.apiKey)}`;
                const response = await fetch(url, {
                    headers: { 'Authorization': `Bearer ${this.config.apiKey}` }
                });

                if (!response.ok) throw new Error('Failed to load textures');

                const res = await response.json();
                // apiResponse() wraps payload in res.data
                this.displayTextures(Array.isArray(res.data) ? res.data : []);
            } catch (error) {
                console.error('Error loading textures:', error);
                this.displayTextureError();
            }
        }

        /**
         * Display textures WITH CATEGORIES
         */
        displayTextures(textures) {
            const grid = this.modal.querySelector('#epoxy-texture-grid');
        
            if (textures.length === 0) {
                grid.innerHTML = `
                    <div class="epoxy-text-center">
                        <p class="epoxy-text-muted">No approved textures available</p>
                    </div>
                `;
                return;
            }
        
            // Store textures for later reference
            this.textures = textures;
        
            // Group textures by category
            const texturesByCategory = {};
            textures.forEach(texture => {
                const category = texture.category || 'standard';
                if (!texturesByCategory[category]) {
                    texturesByCategory[category] = [];
                }
                texturesByCategory[category].push(texture);
            });
        
            // Define category display names
            const categoryNames = {
                'hardwood': 'Hardwood',
                'tile': 'Tile',
                'carpet': 'Carpet',
                'vinyl': 'Vinyl',
                'laminate': 'Laminate',
                'marble': 'Marble',
                'concrete': 'Concrete',
                'stone': 'Stone',
                'bamboo': 'Bamboo',
                'other': 'Other',
                'standard': 'Standard'
            };
        
            // Create HTML for categorized textures
            let textureHtml = '';
            Object.keys(texturesByCategory).forEach(category => {
                const categoryTextures = texturesByCategory[category];
                const categoryName = categoryNames[category] || category.charAt(0).toUpperCase() + category.slice(1);
        
                textureHtml += `
                    <div class="epoxy-texture-category">
                        <h4 class="epoxy-category-title">${categoryName}</h4>
                        <div class="epoxy-category-grid">
                            ${categoryTextures.map(texture => `
                                <div class="epoxy-grid-item ${this.selectedTexture == texture.id ? 'selected' : ''}"
                                     data-texture-id="${texture.id}"
                                     onclick="this.dispatchEvent(new CustomEvent('select-texture', { detail: ${texture.id}, bubbles: true }))">
                                    <img src="${texture.thumbnail_url || ''}" alt="${texture.name}"
                                         style="width:100%;aspect-ratio:1;object-fit:cover;border-radius:4px;margin-bottom:0.25rem;"
                                         onerror="this.style.background='#e9ecef';this.removeAttribute('src')">
                                    <div style="font-size:0.7rem;font-weight:600;line-height:1.2;text-align:center;overflow:hidden;white-space:nowrap;text-overflow:ellipsis;">${texture.name}</div>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                `;
            });
        
            grid.innerHTML = textureHtml;
        
            // Attach selection listeners
            grid.querySelectorAll('.epoxy-grid-item').forEach(item => {
                item.addEventListener('select-texture', (e) => {
                    this.selectedTexture = parseInt(e.detail);
                    this.updateTextureSelection();
                    this.updateNextButton();
                });
            });
        }
        /**
         * Update texture selection UI
         */
        updateTextureSelection() {
            const grid = this.modal.querySelector('#epoxy-texture-grid');
            grid.querySelectorAll('.epoxy-grid-item').forEach(item => {
                const textureId = parseInt(item.dataset.textureId);
                item.classList.toggle('selected', textureId === this.selectedTexture);
            });
        }

        /**
         * Display texture loading error
         */
        displayTextureError() {
            const grid = this.modal.querySelector('#epoxy-texture-grid');
            grid.innerHTML = `
                <div class="epoxy-text-center">
                    <p class="epoxy-text-muted">Failed to load textures</p>
                    <button class="epoxy-btn epoxy-btn-outline" onclick="this.dispatchEvent(new CustomEvent('retry-textures'))">Retry</button>
                </div>
            `;

            // Attach retry listener
            grid.querySelector('button').addEventListener('retry-textures', () => this.loadTextures());
        }

        /**
         * Render custom prompt step
         */
        renderPromptStep() {
            const stepHtml = `
                <div class="epoxy-text-center epoxy-mb-4">
                    <h3>Customize Your Design</h3>
                    <p class="epoxy-text-muted">Add any special instructions (optional)</p>
                </div>

                <div class="epoxy-form-group">
                    <label class="epoxy-form-label">Custom Prompt</label>
                    <textarea
                        class="epoxy-form-control"
                        id="epoxy-custom-prompt"
                        rows="4"
                        placeholder="e.g., Make it more glossy, add metallic flakes, etc."
                    >${this.customPrompt || ''}</textarea>
                    <small class="epoxy-text-muted">Describe any specific style preferences or modifications you'd like</small>
                </div>

                <div class="epoxy-mb-4">
                    <h5>Quick Suggestions:</h5>
                    <div class="epoxy-grid">
                        <button class="epoxy-btn epoxy-btn-outline" onclick="this.dispatchEvent(new CustomEvent('add-prompt', { detail: 'Make it more glossy and reflective' }))">
                            ✨ More Glossy
                        </button>
                        <button class="epoxy-btn epoxy-btn-outline" onclick="this.dispatchEvent(new CustomEvent('add-prompt', { detail: 'Add metallic flake particles' }))">
                            🔶 Add Metallic Flakes
                        </button>
                        <button class="epoxy-btn epoxy-btn-outline" onclick="this.dispatchEvent(new CustomEvent('add-prompt', { detail: 'Create a matte, non-shiny finish' }))">
                            🎨 Matte Finish
                        </button>
                        <button class="epoxy-btn epoxy-btn-outline" onclick="this.dispatchEvent(new CustomEvent('add-prompt', { detail: 'Add subtle color variations' }))">
                            🌈 Color Variations
                        </button>
                    </div>
                </div>
            `;

            // Attach event listeners after rendering
            setTimeout(() => this.attachPromptEventListeners(), 10);

            return stepHtml;
        }

        /**
         * Attach prompt event listeners
         */
        attachPromptEventListeners() {
            const promptTextarea = this.modal.querySelector('#epoxy-custom-prompt');
            const suggestionButtons = this.modal.querySelectorAll('[onclick*="add-prompt"]');

            // Update prompt on input
            if (promptTextarea) {
                promptTextarea.addEventListener('input', (e) => {
                    this.customPrompt = e.target.value;
                });
            }

            // Handle suggestion buttons
            suggestionButtons.forEach(button => {
                button.addEventListener('add-prompt', (e) => {
                    const suggestion = e.detail;
                    if (this.customPrompt) {
                        this.customPrompt += '\n' + suggestion;
                    } else {
                        this.customPrompt = suggestion;
                    }
                    if (promptTextarea) {
                        promptTextarea.value = this.customPrompt;
                    }
                });
            });
        }

        /**
         * Render generation step
         */
        renderGenerationStep() {
            return `
                <div class="epoxy-text-center">
                    <h3>Generating Your Design</h3>
                    <p class="epoxy-text-muted">Our AI is creating your custom flooring visualization</p>

                    <div class="epoxy-progress">
                        <div class="epoxy-progress-bar">
                            <div class="epoxy-progress-fill" id="epoxy-progress-fill"></div>
                        </div>
                        <div class="epoxy-progress-text" id="epoxy-progress-text">Preparing...</div>
                    </div>

                    <div id="epoxy-result-container" class="epoxy-hidden">
                        <img id="epoxy-result-image" class="epoxy-result-image" style="max-width: 100%; border-radius: 8px; margin: 1rem 0;">
                        <div>
                            <button class="epoxy-btn epoxy-btn-primary" onclick="this.dispatchEvent(new CustomEvent('download'))">
                                📥 Download Design
                            </button>
                            <button class="epoxy-btn epoxy-btn-outline" onclick="this.dispatchEvent(new CustomEvent('start-over'))">
                                🔄 Start Over
                            </button>
                        </div>
                    </div>
                </div>
            `;
        }

        /**
         * Start generation process
         */
        async startGeneration() {
            try {
                // Find the selected texture object
                const selectedTextureObj = this.textures.find(t => t.id === this.selectedTexture);
                
                if (!selectedTextureObj) {
                    throw new Error('No texture selected');
                }

                const formData = new FormData();
                
                // Always send as file (either uploaded or camera capture)
                formData.append('image', this.uploadedImage);
                formData.append('texture_id', this.selectedTexture);
                formData.append('texture_name', selectedTextureObj.name);
                formData.append('texture_category', selectedTextureObj.category);
                
                // Add custom prompt if provided
                // if (this.customPrompt) {
                //     formData.append('custom_prompt', this.customPrompt);
                // }

                // Add api_key to FormData as fallback for servers that strip Authorization header
                formData.append('api_key', this.config.apiKey);
                
                const response = await fetch(`${this.sdk.apiBaseUrl}/embed/generate`, {
                    method: 'POST',
                    headers: {
                        'Authorization': `Bearer ${this.config.apiKey}`
                    },
                    body: formData
                });

                if (!response.ok) {
                    const errorData = await response.json();
                    throw new Error(errorData.message || 'Generation failed');
                }

                const result = await response.json();
                
                if (!result.success) {
                    throw new Error(result.message || 'Generation failed');
                }
                
                this.generationId = result.data?.uuid;
                if (!this.generationId) throw new Error('No generation UUID returned');
                this.pollGeneration();
            } catch (error) {
                console.error('Generation error:', error);
                this.showError(error.message || 'Failed to start generation.');
            }
        }

        /**
         * Poll for generation completion
         */
        pollGeneration() {
            let pollCount = 0;
            const maxPolls = 60; // 3 minutes max (60 * 3 seconds)
            let consecutiveErrors = 0;
            const maxConsecutiveErrors = 3;

            const stop = (interval, message = null) => {
                clearInterval(interval);
                if (this.generateBtn) {
                    this.generateBtn.disabled = false;
                    this.generateBtn.textContent = 'Generate Design';
                }
                if (message) this.showError(message);
            };

            const pollInterval = setInterval(async () => {
                try {
                    pollCount++;

                    if (pollCount > maxPolls) {
                        stop(pollInterval, 'Generation timed out — please try again.');
                        return;
                    }

                    const pollUrl = `${this.sdk.apiBaseUrl}/embed/generation/${this.generationId}?api_key=${encodeURIComponent(this.config.apiKey)}`;

                    const response = await fetch(pollUrl, {
                        headers: { 'Authorization': `Bearer ${this.config.apiKey}` }
                    });

                    if (!response.ok) {
                        consecutiveErrors++;
                        console.error('Polling response not OK:', response.status);

                        // Hard-stop on auth/not-found errors, or after 3 consecutive server errors
                        if (response.status === 401 || response.status === 404 || consecutiveErrors >= maxConsecutiveErrors) {
                            let msg = 'Generation failed — please try again.';
                            try { const e = await response.json(); if (e.message) msg = e.message; } catch (_) {}
                            stop(pollInterval, msg);
                        }
                        return;
                    }

                    consecutiveErrors = 0;

                    const envelope = await response.json();

                    if (envelope.success === false) {
                        stop(pollInterval, envelope.message || 'Generation failed — please try again.');
                        return;
                    }

                    const generation = envelope.data || {};

                    console.log('Generation status:', generation);

                    this.updateProgress(generation.status, generation.progress || 0);

                    if (generation.status === 'completed') {
                        stop(pollInterval);
                        this.showResult(generation);
                    } else if (generation.status === 'failed' || generation.completed) {
                        stop(pollInterval, generation.error_message || 'Generation failed — please try again.');
                    }

                } catch (error) {
                    consecutiveErrors++;
                    console.error('Polling error:', error);
                    if (consecutiveErrors >= maxConsecutiveErrors) {
                        stop(pollInterval, 'Lost connection to server — please try again.');
                    }
                }
            }, 3000);
        }

        /**
         * Update progress display
         */
        updateProgress(status, progress = 0) {
            const fill = this.modal.querySelector('#epoxy-progress-fill');
            const text = this.modal.querySelector('#epoxy-progress-text');

            const progressText = {
                'pending': 'Preparing your image...',
                'queued': 'Waiting in queue...',
                'processing': `Processing... ${Math.round(progress)}%`,
                'completed': 'Complete!',
                'failed': 'Failed'
            };

            if (text) {
                text.textContent = progressText[status] || status;
            }
            
            if (fill) {
                fill.style.width = `${progress}%`;
            }
        }

        /**
         * Show generation result
         */
        showResult(data) {
            const container = this.modal.querySelector('#epoxy-result-container');
            const image = this.modal.querySelector('#epoxy-result-image');

            // Use preview URL for display (smaller, faster loading)
            const displayUrl = data.preview_url || data.output_url;
            // Use HD URL for download (high quality)
            const downloadUrl = data.hd_url || data.output_url;
            
            image.src = displayUrl;
            container.classList.remove('epoxy-hidden');

            // Add download functionality - downloads HD version
            this.modal.querySelector('[onclick*="download"]').onclick = () => {
                this.downloadImage(downloadUrl);
            };

            // Add start over functionality
            this.modal.querySelector('[onclick*="start-over"]').onclick = () => {
                this.reset();
            };
        }
        
        /**
         * Show error message on modal
         */
        showError(errorMessage) {
            const content = this.modal?.querySelector('#epoxy-modal-content');
            if (!content) {
                // Modal not open — fall back to toast
                this.sdk.showError(errorMessage);
                return;
            }

            // Replace step content entirely so there's no hidden-element race condition
            content.innerHTML = `
                <div style="text-align:center;padding:2rem 1rem;">
                    <div style="font-size:3rem;margin-bottom:0.75rem;">❌</div>
                    <h3 style="margin:0 0 0.5rem;color:#c53030;">Generation Failed</h3>
                    <p style="margin:0 0 1.5rem;color:#742a2a;font-size:0.9rem;">${errorMessage}</p>
                    <button id="epoxy-try-again-btn"
                        style="padding:0.6rem 1.5rem;background:#e53e3e;color:#fff;border:none;border-radius:6px;font-size:0.95rem;cursor:pointer;">
                        🔄 Try Again
                    </button>
                </div>
            `;

            content.querySelector('#epoxy-try-again-btn').addEventListener('click', () => {
                this.currentStep = 1;
                this.renderStep();
            });

            // Show footer Back button so user isn't fully stuck
            const prevBtn = this.modal.querySelector('#epoxy-prev-btn');
            const nextBtn = this.modal.querySelector('#epoxy-next-btn');
            if (prevBtn) prevBtn.classList.remove('epoxy-hidden');
            if (nextBtn) nextBtn.classList.add('epoxy-hidden');
        }

        /**
         * Download image
         */
        downloadImage(url) {
            const a = document.createElement('a');
            a.href = url;
            a.download = 'epoxy-design.jpg';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
        }

        /**
         * Reset modal to initial state
         */
        reset() {
            this.currentStep = 1;
            this.uploadedImage = null;
            this.selectedTexture = null;
            // this.customPrompt = '';
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