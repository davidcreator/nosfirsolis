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