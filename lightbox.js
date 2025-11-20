// lightbox.js - Lightbox do wyświetlania zdjęć w pełnym rozmiarze
// Dodaj ten skrypt do reports.php

function createLightbox() {
    const lightbox = document.createElement('div');
    lightbox.id = 'lightbox';
    lightbox.style.cssText = `
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.9);
        z-index: 20000;
        align-items: center;
        justify-content: center;
        backdrop-filter: blur(4px);
    `;
    
    lightbox.innerHTML = `
        <div style="position: relative; max-width: 90%; max-height: 90%; display: flex; align-items: center; justify-content: center;">
            <img id="lightboxImage" style="max-width: 100%; max-height: 100%; border-radius: 12px; box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);">
            <button onclick="closeLightbox()" style="position: absolute; top: -40px; right: -40px; background: white; border: none; border-radius: 50%; width: 40px; height: 40px; cursor: pointer; font-size: 20px; color: #333; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);">&times;</button>
        </div>
        <div style="position: absolute; bottom: 30px; left: 50%; transform: translateX(-50%); color: white; text-align: center; background: rgba(0, 0, 0, 0.7); padding: 10px 20px; border-radius: 20px;">
            <div id="lightboxTitle" style="font-weight: 600; margin-bottom: 5px;"></div>
            <div id="lightboxMeta" style="font-size: 12px; opacity: 0.8;"></div>
        </div>
    `;
    
    document.body.appendChild(lightbox);
    
    // Close on background click
    lightbox.addEventListener('click', function(e) {
        if (e.target === lightbox) {
            closeLightbox();
        }
    });
    
    // Close on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && lightbox.style.display === 'flex') {
            closeLightbox();
        }
    });
}

function openLightbox(imageSrc, title, meta) {
    let lightbox = document.getElementById('lightbox');
    if (!lightbox) {
        createLightbox();
        lightbox = document.getElementById('lightbox');
    }
    
    const lightboxImage = document.getElementById('lightboxImage');
    const lightboxTitle = document.getElementById('lightboxTitle');
    const lightboxMeta = document.getElementById('lightboxMeta');
    
    lightboxImage.src = imageSrc;
    lightboxTitle.textContent = title || 'Załącznik';
    lightboxMeta.textContent = meta || '';
    
    lightbox.style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeLightbox() {
    const lightbox = document.getElementById('lightbox');
    if (lightbox) {
        lightbox.style.display = 'none';
        document.body.style.overflow = 'auto';
    }
}

// Zmodyfikuj funkcję viewAttachment w reports.php
function viewAttachmentEnhanced(filename, originalName, fileType, fileSize) {
    if (fileType.startsWith('image/')) {
        openLightbox(
            `upload_handler.php?file=${filename}`,
            originalName,
            formatFileSize(fileSize)
        );
    } else {
        window.open(`upload_handler.php?file=${filename}`, '_blank');
    }
}