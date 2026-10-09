import './bootstrap';

/**
 * Global helper untuk menyalin teks ke clipboard dengan dukungan fallback
 * dan trigger notifikasi floating toast / snackbar.
 */
window.copyToClipboard = function (text, successMsg = 'Tautan berhasil disalin!') {
    if (!text) return;

    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text).then(() => {
            window.dispatchEvent(new CustomEvent('toast', { 
                detail: { message: successMsg, type: 'success' } 
            }));
        }).catch(() => {
            fallbackCopy(text, successMsg);
        });
    } else {
        fallbackCopy(text, successMsg);
    }
};

function fallbackCopy(text, successMsg) {
    try {
        const textArea = document.createElement('textarea');
        textArea.value = text;
        textArea.style.position = 'fixed';
        textArea.style.left = '-999999px';
        textArea.style.top = '-999999px';
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();
        const successful = document.execCommand('copy');
        textArea.remove();
        
        if (successful) {
            window.dispatchEvent(new CustomEvent('toast', { 
                detail: { message: successMsg, type: 'success' } 
            }));
        } else {
            window.dispatchEvent(new CustomEvent('toast', { 
                detail: { message: 'Gagal menyalin teks ke clipboard.', type: 'error' } 
            }));
        }
    } catch (err) {
        window.dispatchEvent(new CustomEvent('toast', { 
            detail: { message: 'Gagal menyalin tautan.', type: 'error' } 
        }));
    }
}
