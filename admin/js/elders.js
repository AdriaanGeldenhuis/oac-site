(function() {
    'use strict';
    
    // Wait for TinyMCE to load
    if (typeof tinymce === 'undefined') {
        console.error('TinyMCE not loaded!');
        return;
    }
    
    let editorAf = null;
    let editorEn = null;
    let currentEditor = null;
    let bibleData = null;
    let hasChanges = false;
    
    const CFG = window.ELDER_CONFIG;
    const T = (key) => CFG.translations[CFG.lang][key] || key;
    
    // TinyMCE Configuration
    const editorConfig = {
        height: 600,
        menubar: 'file edit view insert format tools table',
        plugins: [
            'advlist', 'autolink', 'lists', 'link', 'image', 'charmap', 'preview',
            'anchor', 'searchreplace', 'visualblocks', 'code', 'fullscreen',
            'insertdatetime', 'media', 'table', 'wordcount', 'paste'
        ],
        toolbar: 'undo redo | formatselect | bold italic underline forecolor backcolor | ' +
                 'alignleft aligncenter alignright alignjustify | ' +
                 'bullist numlist outdent indent | removeformat | table | code',
        toolbar_mode: 'sliding',
        content_style: `
            body {
                font-family: Georgia, serif;
                font-size: 16px;
                line-height: 1.8;
                color: #333;
                background: white;
                padding: 40px;
                max-width: 900px;
                margin: 0 auto;
            }
            h1 { 
                font-size: 2.5em; 
                color: #f3c3b1; 
                margin: 1em 0 0.5em; 
                font-family: 'Parisienne', cursive; 
            }
            h2 { 
                font-size: 2em; 
                color: #d4939e; 
                margin: 1em 0 0.5em; 
            }
            h3 { 
                font-size: 1.5em; 
                color: #b76e79; 
                margin: 0.8em 0 0.4em; 
            }
            p { 
                margin: 1em 0; 
            }
            .verse-ref { 
                color: #f3c3b1; 
                font-weight: bold; 
                font-size: 1.1em; 
                margin: 1.5em 0 0.5em; 
                display: block; 
            }
            .verse-text { 
                font-style: italic; 
                color: #555; 
                line-height: 1.9; 
            }
            .verse-text sup { 
                color: #f3c3b1; 
                font-weight: bold; 
            }
        `,
        paste_as_text: false,
        paste_word_valid_elements: 'p,b,strong,i,em,h1,h2,h3,h4,ul,ol,li,a[href],span,sup,sub',
        font_formats: 'Georgia=georgia,serif; Parisienne=parisienne,cursive; ' +
                     'Dancing Script=dancing script,cursive; Arial=arial,helvetica,sans-serif; ' +
                     'Times New Roman=times new roman,times,serif',
        fontsize_formats: '8pt 10pt 12pt 14pt 16pt 18pt 24pt 36pt 48pt',
        block_formats: 'Paragraph=p; Heading 1=h1; Heading 2=h2; Heading 3=h3',
        statusbar: true,
        branding: false,
        resize: true,
        setup: function(editor) {
            editor.on('init', function() {
                console.log('Editor initialized:', editor.id);
                if (editor.id === 'editor-af') {
                    editorAf = editor;
                    if (CFG.lang === 'af') {
                        currentEditor = editorAf;
                        console.log('Current editor set to AF');
                    }
                } else if (editor.id === 'editor-en') {
                    editorEn = editor;
                    if (CFG.lang === 'en') {
                        currentEditor = editorEn;
                        console.log('Current editor set to EN');
                    }
                }
            });
            
            editor.on('change keyup', function() {
                hasChanges = true;
            });
        }
    };
    
    // Initialize TinyMCE
    function initEditors() {
        console.log('Initializing editors...');
        tinymce.init({ ...editorConfig, selector: '#editor-af' });
        tinymce.init({ ...editorConfig, selector: '#editor-en' });
    }
    
    // Language Switch
    function setupLanguageSwitch() {
        const langBtns = document.querySelectorAll('.lang-btn');
        console.log('Language buttons found:', langBtns.length);
        
        langBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                console.log('Language button clicked:', this.dataset.lang);
                const newLang = this.dataset.lang;
                CFG.lang = newLang;
                
                langBtns.forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                
                if (newLang === 'af') {
                    currentEditor = editorAf;
                    document.getElementById('editor-box-af').style.display = 'block';
                    document.getElementById('editor-box-en').style.display = 'none';
                } else {
                    currentEditor = editorEn;
                    document.getElementById('editor-box-en').style.display = 'block';
                    document.getElementById('editor-box-af').style.display = 'none';
                }
                
                loadBible();
            });
        });
    }
    
    // Load Bible Data
    async function loadBible() {
        const filename = (CFG.lang === 'en') ? 'en_kjv1611.json' : 'af_1933_53.json';
        console.log('Loading Bible:', filename);
        try {
            const response = await fetch(`/bible/bibles/${filename}`);
            bibleData = await response.json();
            console.log('Bible loaded successfully');
        } catch (error) {
            console.error('Bible load error:', error);
            showStatus('error', 'Failed to load Bible');
        }
    }
    
    // Verse Modal
    function setupVerseModal() {
        const modal = document.getElementById('verse-modal');
        const btnVerse = document.getElementById('btn-verse');
        const btnInsert = document.getElementById('btn-insert');
        const closeBtn = modal.querySelector('.modal-close');
        
        const bookSel = document.getElementById('v-book');
        const chapterSel = document.getElementById('v-chapter');
        const fromSel = document.getElementById('v-from');
        const toSel = document.getElementById('v-to');
        const preview = document.getElementById('v-preview');
        
        console.log('Verse modal elements found');
        
        // Open modal
        btnVerse.addEventListener('click', function() {
            console.log('Verse button clicked');
            if (!bibleData) {
                alert('Bible not loaded yet');
                return;
            }
            
            bookSel.innerHTML = '<option value="">Kies...</option>';
            Object.keys(bibleData).forEach(book => {
                const option = document.createElement('option');
                option.value = book;
                option.textContent = book;
                bookSel.appendChild(option);
            });
            
            chapterSel.innerHTML = '';
            fromSel.innerHTML = '';
            toSel.innerHTML = '';
            preview.innerHTML = '';
            
            modal.classList.add('show');
            console.log('Modal opened');
        });
        
        // Book change
        bookSel.addEventListener('change', function() {
            console.log('Book selected:', this.value);
            const book = this.value;
            chapterSel.innerHTML = '<option value="">Kies...</option>';
            fromSel.innerHTML = '';
            toSel.innerHTML = '';
            preview.innerHTML = '';
            
            if (book && bibleData[book]) {
                Object.keys(bibleData[book]).forEach(chapter => {
                    const option = document.createElement('option');
                    option.value = chapter;
                    option.textContent = chapter;
                    chapterSel.appendChild(option);
                });
            }
        });
        
        // Chapter change
        chapterSel.addEventListener('change', function() {
            console.log('Chapter selected:', this.value);
            const book = bookSel.value;
            const chapter = this.value;
            fromSel.innerHTML = '<option value="">Kies...</option>';
            toSel.innerHTML = '<option value="">Kies...</option>';
            preview.innerHTML = '';
            
            if (book && chapter && bibleData[book][chapter]) {
                const verses = bibleData[book][chapter].filter(v => v.n);
                verses.forEach(verse => {
                    const opt1 = document.createElement('option');
                    opt1.value = verse.n;
                    opt1.textContent = verse.n;
                    fromSel.appendChild(opt1);
                    
                    const opt2 = document.createElement('option');
                    opt2.value = verse.n;
                    opt2.textContent = verse.n;
                    toSel.appendChild(opt2);
                });
            }
        });
        
        // Preview update
        function updatePreview() {
            const book = bookSel.value;
            const chapter = chapterSel.value;
            const from = parseInt(fromSel.value);
            const to = parseInt(toSel.value) || from;
            
            if (!book || !chapter || !from) {
                preview.innerHTML = '';
                return;
            }
            
            const verses = bibleData[book][chapter].filter(v => v.n);
            let html = `<strong>${book} ${chapter}:${from}${to > from ? '-' + to : ''}</strong><br><br>`;
            
            verses.forEach(verse => {
                const num = parseInt(verse.n);
                if (num >= from && num <= to) {
                    html += `<sup>${num}</sup> ${verse.v} `;
                }
            });
            
            preview.innerHTML = html;
            console.log('Preview updated');
        }
        
        fromSel.addEventListener('change', updatePreview);
        toSel.addEventListener('change', updatePreview);
        
        // Insert verse
        btnInsert.addEventListener('click', function() {
            console.log('Insert button clicked');
            const book = bookSel.value;
            const chapter = chapterSel.value;
            const from = parseInt(fromSel.value);
            const to = parseInt(toSel.value) || from;
            
            if (!book || !chapter || !from) {
                alert(T('selectAll'));
                return;
            }
            
            if (!currentEditor) {
                console.error('No current editor!');
                alert('Editor not ready');
                return;
            }
            
            const verses = bibleData[book][chapter].filter(v => v.n);
            let html = `<p class="verse-ref">${book} ${chapter}:${from}${to > from ? '-' + to : ''}</p><p class="verse-text">`;
            
            verses.forEach(verse => {
                const num = parseInt(verse.n);
                if (num >= from && num <= to) {
                    html += `<sup>${num}</sup> ${verse.v} `;
                }
            });
            
            html += '</p>';
            
            currentEditor.insertContent(html);
            modal.classList.remove('show');
            showStatus('saved', T('inserted'));
            console.log('Verse inserted');
        });
        
        // Close modal
        closeBtn.addEventListener('click', () => {
            modal.classList.remove('show');
            console.log('Modal closed');
        });
        
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.classList.remove('show');
                console.log('Modal closed by backdrop');
            }
        });
    }
    
    // Improve with AI
    function setupImprove() {
        const btnImprove = document.getElementById('btn-improve');
        console.log('Improve button found');
        
        btnImprove.addEventListener('click', async function() {
            console.log('Improve button clicked');
            
            if (!currentEditor) {
                console.error('No current editor!');
                return;
            }
            
            const content = currentEditor.getContent();
            if (!content.trim()) {
                console.log('No content to improve');
                return;
            }
            
            showStatus('saving', T('improving'));
            
            try {
                const formData = new URLSearchParams();
                formData.append('content', content);
                formData.append('lang', CFG.lang);
                
                console.log('Sending improve request...');
                const response = await fetch('/admin/api/ai/improve.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                console.log('Improve response:', data);
                
                if (data.success) {
                    currentEditor.setContent(data.improved);
                    showStatus('saved', T('improved'));
                    hasChanges = true;
                } else {
                    throw new Error(data.error);
                }
            } catch (error) {
                console.error('Improve error:', error);
                showStatus('error', error.message);
            }
        });
    }
    
    // Save & Translate
    function setupSave() {
        const btnSave = document.getElementById('btn-save');
        console.log('Save button found');
        
        btnSave.addEventListener('click', async function() {
            console.log('Save button clicked');
            
            if (!currentEditor || !editorAf || !editorEn) {
                console.error('Editors not ready!');
                alert('Please wait for editors to load');
                return;
            }
            
            const sourceContent = currentEditor.getContent();
            if (!sourceContent.trim()) {
                console.log('No content to save');
                return;
            }
            
            showStatus('saving', T('saving'));
            
            try {
                // Step 1: Translate
                const fd1 = new URLSearchParams();
                fd1.append('content', sourceContent);
                fd1.append('from_lang', CFG.lang);
                
                console.log('Sending translate request...');
                const res1 = await fetch('/admin/api/ai/translate.php', {
                    method: 'POST',
                    body: fd1
                });
                
                const data1 = await res1.json();
                console.log('Translate response:', data1);
                
                if (!data1.success) throw new Error(data1.error);
                
                // Update other editor
                const targetEditor = (CFG.lang === 'af') ? editorEn : editorAf;
                targetEditor.setContent(data1.translated);
                console.log('Target editor updated');
                
                // Step 2: Save both
                const fd2 = new URLSearchParams();
                fd2.append('content_af', editorAf.getContent());
                fd2.append('content_en', editorEn.getContent());
                fd2.append('town_id', CFG.townId);
                
                console.log('Sending save request...');
                const res2 = await fetch('/admin/api/elders/save_teaching.php', {
                    method: 'POST',
                    body: fd2
                });
                
                const data2 = await res2.json();
                console.log('Save response:', data2);
                
                if (data2.success) {
                    showStatus('saved', T('saved'));
                    hasChanges = false;
                } else {
                    throw new Error(data2.error);
                }
            } catch (error) {
                console.error('Save error:', error);
                showStatus('error', error.message);
            }
        });
    }
    
    // Show Status
    function showStatus(type, message) {
        const badge = document.getElementById('status');
        const icon = document.getElementById('status-icon');
        const text = document.getElementById('status-text');
        
        badge.className = 'status-badge ' + type;
        
        if (type === 'saving') {
            icon.textContent = '⏳';
        } else if (type === 'saved') {
            icon.textContent = '✅';
        } else if (type === 'error') {
            icon.textContent = '❌';
        }
        
        text.textContent = message;
        console.log('Status:', type, message);
    }
    
    // Prevent loss
    window.addEventListener('beforeunload', function(e) {
        if (hasChanges) {
            e.preventDefault();
            e.returnValue = '';
        }
    });
    
    // Initialize everything
    console.log('Starting initialization...');
    
    // Wait for DOM
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
    
    function init() {
        console.log('DOM ready, initializing...');
        initEditors();
        setupLanguageSwitch();
        setupVerseModal();
        setupImprove();
        setupSave();
        loadBible();
        console.log('Initialization complete');
    }
    
})();