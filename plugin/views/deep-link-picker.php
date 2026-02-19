<?php
/**
 * Deep Linking Content Picker View
 *
 * This interface allows instructors to select Pressbooks content
 * during LMS activity creation (Deep Linking 2.0 flow)
 */

defined('ABSPATH') || exit;

// Extract data passed from controller
$books = $data['books'] ?? [];
$deep_link_return_url = $data['return_url'] ?? '';
$client_id = $data['client_id'] ?? '';
$deployment_id = $data['deployment_id'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Select Pressbooks Content</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .header {
            background: #2271b1;
            color: white;
            padding: 20px 30px;
        }

        .header h1 {
            font-size: 24px;
            margin-bottom: 5px;
        }

        .header p {
            opacity: 0.9;
            font-size: 14px;
        }

        .content {
            padding: 30px;
        }

        .book-list {
            display: grid;
            gap: 15px;
            margin-bottom: 20px;
        }

        .book-card {
            border: 2px solid #ddd;
            border-radius: 6px;
            padding: 20px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .book-card:hover {
            border-color: #2271b1;
            background: #f8fafc;
        }

        .book-card.selected {
            border-color: #2271b1;
            background: #e7f3ff;
        }

        .book-title {
            font-size: 18px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 8px;
        }

        .book-description {
            color: #64748b;
            font-size: 14px;
            margin-bottom: 10px;
        }

        .book-url {
            font-size: 12px;
            color: #2271b1;
            font-family: monospace;
        }

        .expand-btn {
            margin-top: 10px;
            background: #2271b1;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }

        .expand-btn:hover {
            background: #135e96;
        }

        .chapter-list {
            margin-top: 15px;
            padding: 15px;
            background: #f8fafc;
            border-radius: 4px;
            display: none;
        }

        .chapter-list.visible {
            display: block;
        }

        .chapter-item {
            padding: 10px;
            margin: 5px 0;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .chapter-item:hover {
            background: #e7f3ff;
            border-color: #2271b1;
        }

        .chapter-item.selected {
            background: #e7f3ff;
            border-color: #2271b1;
            font-weight: 600;
        }

        .chapter-type {
            display: inline-block;
            padding: 2px 8px;
            background: #94a3b8;
            color: white;
            border-radius: 3px;
            font-size: 11px;
            text-transform: uppercase;
            margin-right: 8px;
        }

        .chapter-type.chapter {
            background: #2271b1;
        }

        .chapter-type.front-matter {
            background: #7c3aed;
        }

        .chapter-type.back-matter {
            background: #ea580c;
        }

        .part-heading {
            font-weight: 600;
            color: #475569;
            margin: 15px 0 10px;
            padding-bottom: 5px;
            border-bottom: 2px solid #e2e8f0;
        }

        .actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-primary {
            background: #2271b1;
            color: white;
        }

        .btn-primary:hover:not(:disabled) {
            background: #135e96;
        }

        .btn-primary:disabled {
            background: #cbd5e1;
            cursor: not-allowed;
        }

        .btn-secondary {
            background: white;
            color: #475569;
            border: 1px solid #cbd5e1;
        }

        .btn-secondary:hover {
            background: #f8fafc;
        }

        .loading {
            text-align: center;
            padding: 40px;
            color: #64748b;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #64748b;
        }

        .empty-state-icon {
            font-size: 48px;
            margin-bottom: 16px;
        }

        /* Tabs Navigation */
        .tabs {
            display: flex;
            border-bottom: 2px solid #e2e8f0;
            margin: 0 -30px 25px;
            padding: 0 30px;
        }

        .tab-btn {
            padding: 15px 25px;
            cursor: pointer;
            border: none;
            background: none;
            font-size: 15px;
            font-weight: 600;
            color: #64748b;
            border-bottom: 3px solid transparent;
            margin-bottom: -2px;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .tab-btn:hover {
            color: #2271b1;
            background: #f8fafc;
        }

        .tab-btn.active {
            color: #2271b1;
            border-bottom-color: #2271b1;
        }

        .tab-panel {
            display: none;
        }

        .tab-panel.active {
            display: block;
        }

        .selection-info {
            background: #f0f9ff;
            border: 1px solid #bae6fd;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 20px;
            display: none;
        }

        .selection-info.visible {
            display: block;
        }

        .selection-info strong {
            color: #0369a1;
        }

        /* Chapter Confirmation Modal */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal {
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
            max-width: 700px;
            width: 90%;
            max-height: 80vh;
            display: flex;
            flex-direction: column;
        }

        .modal-header {
            background: #2271b1;
            color: white;
            padding: 20px;
            border-radius: 8px 8px 0 0;
        }

        .modal-header h2 {
            margin: 0;
            font-size: 20px;
        }

        .modal-body {
            padding: 20px;
            overflow-y: auto;
            flex: 1;
        }

        .modal-actions {
            padding: 15px 20px;
            border-top: 1px solid #e5e7eb;
            display: flex;
            gap: 10px;
            justify-content: space-between;
            align-items: center;
        }

        .chapter-checkbox-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .chapter-checkbox-item {
            padding: 12px;
            border: 1px solid #e5e7eb;
            border-radius: 4px;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            transition: background 0.2s;
        }

        .chapter-checkbox-item:hover {
            background: #f9fafb;
        }

        .chapter-checkbox-item input[type="checkbox"] {
            width: 18px;
            height: 18px;
            margin-right: 12px;
            cursor: pointer;
        }

        .chapter-checkbox-item label {
            cursor: pointer;
            flex: 1;
            user-select: none;
        }

        .chapter-type-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            margin-right: 8px;
        }

        .badge-front {
            background: #dbeafe;
            color: #1e40af;
        }

        .badge-chapter {
            background: #dcfce7;
            color: #166534;
        }

        .badge-back {
            background: #fef3c7;
            color: #92400e;
        }

        .bulk-actions {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
        }

        .btn-small {
            padding: 6px 12px;
            font-size: 13px;
            border-radius: 4px;
            border: 1px solid #d1d5db;
            background: white;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-small:hover {
            background: #f3f4f6;
            border-color: #9ca3af;
        }

        .selected-count {
            color: #6b7280;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📚 Select Pressbooks Content</h1>
            <p>Choose a book or specific chapter to link in your course</p>
        </div>

        <div class="content">
            <div class="tabs">
                <button class="tab-btn active" onclick="switchTab('content-picker')">📖 Content Picker</button>
                <button class="tab-btn" onclick="switchTab('results-viewer-picker')">📊 Results Viewers</button>
            </div>

            <!-- Tab: Content Picker -->
            <div id="content-picker" class="tab-panel active">
                <div id="selection-info-content" class="selection-info" style="margin-bottom: 20px; display: none;">
                    <strong>Selected:</strong> <span id="selected-title-content">None</span>
                </div>

                <?php if (empty($books)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">📖</div>
                        <h2>No Books Found</h2>
                        <p>There are no published books in this Pressbooks network yet.</p>
                    </div>
                <?php else: ?>
                    <div class="book-list" id="book-list-content">
                        <?php foreach ($books as $book): ?>
                            <div class="book-card" data-book-id="<?php echo esc_attr($book['id']); ?>" data-book-title="<?php echo esc_attr($book['title']); ?>">
                                <div class="book-title"><?php echo esc_html($book['title']); ?></div>
                                <?php if (!empty($book['description'])): ?>
                                    <div class="book-description"><?php echo esc_html($book['description']); ?></div>
                                <?php endif; ?>
                                <div class="book-url"><?php echo esc_html($book['url']); ?></div>
                                <div style="margin-top: 15px;">
                                    <button class="expand-btn" onclick="loadChapters(<?php echo esc_attr($book['id']); ?>, event)">
                                        📚 View Chapters
                                    </button>
                                </div>
                                <div class="chapter-list" id="chapters-<?php echo esc_attr($book['id']); ?>">
                                    <div class="loading">Loading chapters...</div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="actions">
                    <button class="btn btn-secondary" onclick="window.history.back()">Cancel</button>
                    <button class="btn btn-primary" id="submit-btn" onclick="submitSelection()" disabled>
                        Select This Content
                    </button>
                </div>
            </div>

            <!-- Tab: Results Viewer Picker -->
            <div id="results-viewer-picker" class="tab-panel">
                <p style="margin-bottom: 20px; color: #64748b; font-size: 14px;">Select a book below to add a <strong>Results Viewer</strong> for that book to your course. Instructors can use this to track student progress and H5P scores.</p>
                
                <div class="book-list" id="book-list-results">
                    <?php foreach ($books as $book): ?>
                        <div class="book-card" style="display: flex; justify-content: space-between; align-items: center;" onclick="submitResultsViewer(<?php echo esc_attr($book['id']); ?>, '<?php echo esc_attr($book['title']); ?>', event)">
                            <div>
                                <div class="book-title" style="margin-bottom: 0;"><?php echo esc_html($book['title']); ?></div>
                                <div class="book-url"><?php echo esc_html($book['url']); ?></div>
                            </div>
                            <button class="btn btn-primary" style="background: #166534;">
                                Add Viewer
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="actions">
                    <button class="btn btn-secondary" onclick="window.history.back()">Cancel</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Chapter Selection Confirmation Modal -->
    <div class="modal-overlay" id="chapter-modal">
        <div class="modal">
            <div class="modal-header">
                <h2>📖 Select Chapters to Add</h2>
                <p style="margin: 5px 0 0 0; opacity: 0.9; font-size: 14px;">Choose which chapters to add as activities</p>
            </div>
            <div class="modal-body">
                <div class="bulk-actions">
                    <button class="btn-small" onclick="selectAllChapters()">✓ Select All</button>
                    <button class="btn-small" onclick="deselectAllChapters()">✗ Deselect All</button>
                    <span class="selected-count" id="selected-count"></span>
                </div>
                <ul class="chapter-checkbox-list" id="chapter-checkbox-list">
                    <!-- Populated by JavaScript -->
                </ul>
            </div>
            <div class="modal-actions">
                <button class="btn btn-secondary" onclick="closeChapterModal()">Cancel</button>
                <button class="btn btn-primary" onclick="confirmChapterSelection()">Add Selected Chapters</button>
            </div>
        </div>
    </div>

    <form id="selection-form" method="POST" action="<?php echo esc_url(rest_url('pb-lti/v1/deep-link')); ?>" style="display: none;">
        <input type="hidden" name="deep_link_return_url" value="<?php echo esc_attr($deep_link_return_url); ?>">
        <input type="hidden" name="client_id" value="<?php echo esc_attr($client_id); ?>">
        <input type="hidden" name="deployment_id" value="<?php echo esc_attr($deployment_id); ?>">
        <input type="hidden" name="selected_book_id" id="selected_book_id">
        <input type="hidden" name="selected_content_id" id="selected_content_id">
        <input type="hidden" name="selected_title" id="form_selected_title">
        <input type="hidden" name="selected_url" id="form_selected_url">
        <input type="hidden" name="selected_chapter_ids" id="selected_chapter_ids">
        <input type="hidden" name="is_results_viewer" id="is_results_viewer" value="0">
    </form>

    <script>
        let selectedBook = null;
        let selectedContent = null;

        function switchTab(tabId) {
            // Update buttons
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
                if (btn.getAttribute('onclick').includes(tabId)) {
                    btn.classList.add('active');
                }
            });

            // Update panels
            document.querySelectorAll('.tab-panel').forEach(panel => {
                panel.classList.remove('active');
            });
            document.getElementById(tabId).classList.add('active');
        }

        // Handle book card clicks in content picker tab
        document.querySelectorAll('#book-list-content .book-card').forEach(card => {
            card.addEventListener('click', function(e) {
                // Don't trigger if clicking the expand button
                if (e.target.classList.contains('expand-btn')) {
                    return;
                }

                selectBook(this);
            });
        });

        function selectBook(card) {
            // Clear previous selections
            document.querySelectorAll('#book-list-content .book-card').forEach(c => c.classList.remove('selected'));
            document.querySelectorAll('.chapter-item').forEach(c => c.classList.remove('selected'));

            card.classList.add('selected');

            selectedBook = {
                id: card.dataset.bookId,
                title: card.dataset.bookTitle,
                url: card.dataset.bookUrl
            };
            selectedContent = null;
            document.getElementById('is_results_viewer').value = '0'; // Reset

            updateSelection();
        }

        function submitResultsViewer(bookId, bookTitle, event) {
            event.stopPropagation();
            // No confirm needed now that it's in its own tab
            document.getElementById('selected_book_id').value = bookId;
            document.getElementById('is_results_viewer').value = '1';
            document.getElementById('selection-form').submit();
        }

        function loadChapters(bookId, event) {
            event.stopPropagation();

            const chapterList = document.getElementById('chapters-' + bookId);
            const isVisible = chapterList.classList.contains('visible');

            // Close all other chapter lists
            document.querySelectorAll('.chapter-list').forEach(list => {
                list.classList.remove('visible');
            });

            if (isVisible) {
                return; // Already loaded, just toggle
            }

            chapterList.classList.add('visible');

            // Fetch chapters via AJAX (use full URL for Bedrock compatibility)
            fetch('<?php echo esc_url(admin_url('admin-ajax.php')); ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=pb_lti_get_book_structure&book_id=' + bookId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    renderChapters(bookId, data.data);
                } else {
                    chapterList.innerHTML = '<p>Error loading chapters</p>';
                }
            })
            .catch(error => {
                chapterList.innerHTML = '<p>Error loading chapters</p>';
            });
        }

        function renderChapters(bookId, structure) {
            const chapterList = document.getElementById('chapters-' + bookId);
            let html = '';

            // Front matter
            if (structure.front_matter && structure.front_matter.length > 0) {
                html += '<div class="part-heading">Front Matter</div>';
                structure.front_matter.forEach(item => {
                    html += `<div class="chapter-item" onclick="selectChapter(${bookId}, ${item.id}, '${escapeHtml(item.title)}', '${escapeHtml(item.url)}', event)">
                        <span class="chapter-type front-matter">Front</span>
                        ${escapeHtml(item.title)}
                    </div>`;
                });
            }

            // Chapters (organized by parts or standalone)
            if (structure.parts && structure.parts.length > 0) {
                structure.parts.forEach(part => {
                    if (part.chapters && part.chapters.length > 0) {
                        html += `<div class="part-heading">${escapeHtml(part.title)}</div>`;
                        part.chapters.forEach(chapter => {
                            html += `<div class="chapter-item" onclick="selectChapter(${bookId}, ${chapter.id}, '${escapeHtml(chapter.title)}', '${escapeHtml(chapter.url)}', event)">
                                <span class="chapter-type chapter">Chapter</span>
                                ${escapeHtml(chapter.title)}
                            </div>`;
                        });
                    }
                });
            }

            // Standalone chapters (not in parts)
            if (structure.chapters && structure.chapters.length > 0) {
                if (html) html += '<div class="part-heading">Chapters</div>';
                structure.chapters.forEach(chapter => {
                    html += `<div class="chapter-item" onclick="selectChapter(${bookId}, ${chapter.id}, '${escapeHtml(chapter.title)}', '${escapeHtml(chapter.url)}', event)">
                        <span class="chapter-type chapter">Chapter</span>
                        ${escapeHtml(chapter.title)}
                    </div>`;
                });
            }

            // Back matter
            if (structure.back_matter && structure.back_matter.length > 0) {
                html += '<div class="part-heading">Back Matter</div>';
                structure.back_matter.forEach(item => {
                    html += `<div class="chapter-item" onclick="selectChapter(${bookId}, ${item.id}, '${escapeHtml(item.title)}', '${escapeHtml(item.url)}', event)">
                        <span class="chapter-type back-matter">Back</span>
                        ${escapeHtml(item.title)}
                    </div>`;
                });
            }

            if (!html) {
                html = '<p style="text-align:center;color:#64748b;">No chapters found in this book</p>';
            }

            chapterList.innerHTML = html;
        }

        function selectChapter(bookId, contentId, title, url, event) {
            event.stopPropagation();

            // Clear previous chapter selections
            document.querySelectorAll('.chapter-item').forEach(c => c.classList.remove('selected'));
            event.currentTarget.classList.add('selected');

            // Ensure book is selected
            const bookCard = document.querySelector(`.book-card[data-book-id="${bookId}"]`);
            if (bookCard && !bookCard.classList.contains('selected')) {
                selectBook(bookCard);
            }

            selectedContent = {
                id: contentId,
                title: title,
                url: url
            };

            updateSelection();
        }

        function updateSelection() {
            const submitBtn = document.getElementById('submit-btn');
            const selectionInfo = document.getElementById('selection-info-content');
            const selectedTitle = document.getElementById('selected-title-content');

            if (selectedBook) {
                submitBtn.disabled = false;
                selectionInfo.style.display = 'block';

                if (selectedContent) {
                    selectedTitle.textContent = selectedContent.title;
                } else {
                    selectedTitle.textContent = selectedBook.title + ' (Entire Book)';
                }
            } else {
                submitBtn.disabled = true;
                selectionInfo.style.display = 'none';
            }
        }

        function submitSelection() {
            if (!selectedBook) {
                alert('Please select a book or chapter');
                return;
            }

            // If specific chapter/content selected, submit directly
            if (selectedContent) {
                document.getElementById('selected_book_id').value = selectedBook.id;
                document.getElementById('selected_content_id').value = selectedContent.id;
                document.getElementById('form_selected_title').value = selectedContent.title;
                document.getElementById('form_selected_url').value = selectedContent.url;
                document.getElementById('selected_chapter_ids').value = '';
                document.getElementById('selection-form').submit();
                return;
            }

            // Whole book selected - show chapter selection modal
            showChapterSelectionModal(selectedBook.id);
        }

        function showChapterSelectionModal(bookId) {
            // Fetch book structure
            fetch('<?php echo esc_url(admin_url('admin-ajax.php')); ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=pb_lti_get_book_structure&book_id=${bookId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    populateChapterCheckboxes(data.data);
                    document.getElementById('chapter-modal').classList.add('active');
                } else {
                    alert('Failed to load chapters: ' + (data.data || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to load chapters');
            });
        }

        function populateChapterCheckboxes(structure) {
            const list = document.getElementById('chapter-checkbox-list');
            list.innerHTML = '';
            let itemIndex = 0;

            // Add front matter
            if (structure.front_matter && structure.front_matter.length > 0) {
                structure.front_matter.forEach(item => {
                    list.appendChild(createChapterCheckbox(item, 'front', itemIndex++));
                });
            }

            // Add chapters
            if (structure.chapters && structure.chapters.length > 0) {
                structure.chapters.forEach(chapter => {
                    list.appendChild(createChapterCheckbox(chapter, 'chapter', itemIndex++));
                });
            }

            // Add back matter
            if (structure.back_matter && structure.back_matter.length > 0) {
                structure.back_matter.forEach(item => {
                    list.appendChild(createChapterCheckbox(item, 'back', itemIndex++));
                });
            }

            updateSelectedCount();
        }

        function createChapterCheckbox(item, type, index) {
            const li = document.createElement('li');
            li.className = 'chapter-checkbox-item';

            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.id = `chapter-${item.id}`;
            checkbox.value = item.id;
            checkbox.checked = true;
            checkbox.onchange = updateSelectedCount;

            const label = document.createElement('label');
            label.htmlFor = `chapter-${item.id}`;

            const badge = document.createElement('span');
            badge.className = `chapter-type-badge badge-${type}`;
            badge.textContent = type === 'front' ? 'Front' : type === 'back' ? 'Back' : 'Chapter';

            label.appendChild(badge);
            label.appendChild(document.createTextNode(item.title));

            li.appendChild(checkbox);
            li.appendChild(label);

            return li;
        }

        function selectAllChapters() {
            document.querySelectorAll('#chapter-checkbox-list input[type="checkbox"]').forEach(cb => {
                cb.checked = true;
            });
            updateSelectedCount();
        }

        function deselectAllChapters() {
            document.querySelectorAll('#chapter-checkbox-list input[type="checkbox"]').forEach(cb => {
                cb.checked = false;
            });
            updateSelectedCount();
        }

        function updateSelectedCount() {
            const checkboxes = document.querySelectorAll('#chapter-checkbox-list input[type="checkbox"]');
            const checked = Array.from(checkboxes).filter(cb => cb.checked).length;
            const total = checkboxes.length;
            document.getElementById('selected-count').textContent = `${checked} of ${total} selected`;
        }

        function closeChapterModal() {
            document.getElementById('chapter-modal').classList.remove('active');
        }

        function confirmChapterSelection() {
            const selectedIds = Array.from(
                document.querySelectorAll('#chapter-checkbox-list input[type="checkbox"]:checked')
            ).map(cb => cb.value);

            if (selectedIds.length === 0) {
                alert('Please select at least one chapter');
                return;
            }

            // Submit with selected chapter IDs
            document.getElementById('selected_book_id').value = selectedBook.id;
            document.getElementById('selected_content_id').value = '';  // Empty for multiple chapters
            document.getElementById('selected_chapter_ids').value = selectedIds.join(',');
            document.getElementById('form_selected_title').value = selectedBook.title;
            document.getElementById('form_selected_url').value = selectedBook.url;

            closeChapterModal();
            document.getElementById('selection-form').submit();
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML.replace(/'/g, "\\'");
        }
    </script>
</body>
</html>
