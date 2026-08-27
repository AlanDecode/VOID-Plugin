(function () {
    if (window.VOIDEditorStatsInitialized) {
        return;
    }
    window.VOIDEditorStatsInitialized = true;

    function ready(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn);
        } else {
            fn();
        }
    }

    function normalizeText(text) {
        return String(text || '')
            .replace(/\u00a0/g, ' ')
            .replace(/\s+/g, ' ')
            .trim();
    }

    function stripRawMarkup(text) {
        return String(text || '')
            .replace(/<!--html-->|<!--markdown-->|<!--more-->/gi, ' ')
            .replace(/```[\s\S]*?```/g, ' ')
            .replace(/~~~[\s\S]*?~~~/g, ' ')
            .replace(/`[^`\n]+`/g, ' ')
            .replace(/\[photos[^\]]*\][\s\S]*?\[\/photos\]/gi, ' ')
            .replace(/\[links[^\]]*\][\s\S]*?\[\/links\]/gi, ' ')
            .replace(/\[notice([^\]]*)\]([\s\S]*?)\[\/notice\]/gi, '$2')
            .replace(/!\[[^\]]*]\([^)]*\)/g, ' ')
            .replace(/\[([^\]]+)\]\(([^)]*)\)/g, '$1')
            .replace(/<img\b[^>]*>/gi, ' ')
            .replace(/<[^>]+>/g, ' ')
            .replace(/#vwid=\d{1,5}&vhei=\d{1,5}/gi, ' ')
            .replace(/\{\{(.+?):(.+?)\}\}/g, '$1')
            .replace(/::\((.*?)\)|:@\((.*?)\)|:&\((.*?)\)|:\$\((.*?)\)|:!\((.*?)\)/g, ' ');
    }

    function getLocalWordStats(textarea) {
        return countWords(normalizeText(stripRawMarkup(textarea ? textarea.value : '')));
    }

    function countMatches(text, pattern) {
        var matches = String(text || '').match(pattern);
        return matches ? matches.length : 0;
    }

    function countWords(text) {
        var chinese = countMatches(text, /[\u3400-\u9fff\uf900-\ufaff]/g);
        var latinWords = countMatches(text, /[A-Za-z]+(?:['’-][A-Za-z]+)*/g);
        var numbers = countMatches(text, /\b\d+(?:\.\d+)?\b/g);

        return {
            total: chinese + latinWords + numbers,
            chinese: chinese,
            latinWords: latinWords,
            numbers: numbers
        };
    }

    function countImages(preview, textarea) {
        if (preview) {
            return preview.querySelectorAll('img').length;
        }

        var source = textarea ? textarea.value : '';
        return countMatches(source, /!\[[^\]]*]\([^)]*\)|<img\b[^>]*>/gi);
    }

    function countTags(tagsInput) {
        if (!tagsInput) {
            return 0;
        }

        return String(tagsInput.value || '')
            .split(',')
            .map(function (item) {
                return item.trim();
            })
            .filter(function (item) {
                return item !== '';
            })
            .length;
    }

    function createMetricCard(label, role) {
        var card = document.createElement('div');
        card.className = 'void-editor-stats-card';
        card.setAttribute('data-role', role);
        card.innerHTML = '<span class="void-editor-stats-label"></span><strong class="void-editor-stats-value">0</strong>';
        card.querySelector('.void-editor-stats-label').textContent = label;
        return card;
    }

    ready(function () {
        var textarea = document.getElementById('text');
        if (!textarea || document.getElementById('void-editor-stats')) {
            return;
        }

        var editArea = document.getElementById('wmd-editarea');
        var preview = document.getElementById('wmd-preview');
        var tagsInput = document.getElementById('tags');
        var anchor = editArea || textarea.parentNode;
        var config = window.VOIDEditorStatsConfig || {};
        var previewUrl = typeof config.previewUrl === 'string' ? config.previewUrl : '';

        if (!anchor || !anchor.parentNode) {
            return;
        }

        var panel = document.createElement('section');
        panel.id = 'void-editor-stats';
        panel.className = 'void-editor-stats';

        var header = document.createElement('div');
        header.className = 'void-editor-stats-header';

        var title = document.createElement('span');
        title.className = 'void-editor-stats-title';
        title.textContent = 'VOID 写作统计';

        var subtitle = document.createElement('span');
        subtitle.className = 'void-editor-stats-subtitle';
        subtitle.textContent = '实时预估当前编辑内容的字数、图片数和标签数';

        header.appendChild(title);
        header.appendChild(subtitle);

        var grid = document.createElement('div');
        grid.className = 'void-editor-stats-grid';
        grid.appendChild(createMetricCard('预估字数', 'words'));
        grid.appendChild(createMetricCard('图片数', 'images'));
        grid.appendChild(createMetricCard('标签数', 'tags'));

        var detail = document.createElement('p');
        detail.className = 'void-editor-stats-detail';

        var note = document.createElement('p');
        note.className = 'void-editor-stats-note';
        note.textContent = '这是写作阶段的实时预估，保存后的前台缓存字数以插件后台统计结果为准。';

        panel.appendChild(header);
        panel.appendChild(grid);
        panel.appendChild(detail);
        panel.appendChild(note);

        anchor.parentNode.insertBefore(panel, anchor.nextSibling);

        var wordValue = panel.querySelector('[data-role="words"] .void-editor-stats-value');
        var imageValue = panel.querySelector('[data-role="images"] .void-editor-stats-value');
        var tagValue = panel.querySelector('[data-role="tags"] .void-editor-stats-value');
        var scheduled = false;
        var composing = false;
        var requestTimer = null;
        var requestSerial = 0;
        var requestController = null;

        function applyWordStats(words) {
            wordValue.textContent = String(words.total);
            detail.textContent = '中文 ' + words.chinese + ' / 英文词 ' + words.latinWords + ' / 数字 ' + words.numbers;
        }

        function syncWordStats() {
            var fallback = getLocalWordStats(textarea);
            var text = textarea ? textarea.value : '';
            var currentSerial = ++requestSerial;

            if (!previewUrl || typeof window.fetch !== 'function') {
                applyWordStats(fallback);
                return;
            }

            if (requestController && typeof requestController.abort === 'function') {
                requestController.abort();
            }

            requestController = typeof window.AbortController === 'function'
                ? new window.AbortController()
                : null;

            window.fetch(previewUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json; charset=utf-8'
                },
                body: JSON.stringify({
                    text: text
                }),
                signal: requestController ? requestController.signal : undefined
            }).then(function (response) {
                if (!response.ok) {
                    throw new Error('wordcount request failed');
                }

                return response.json();
            }).then(function (result) {
                if (currentSerial !== requestSerial) {
                    return;
                }

                if (!result || typeof result.total !== 'number') {
                    applyWordStats(fallback);
                    return;
                }

                applyWordStats({
                    total: result.total,
                    chinese: Number(result.chinese || 0),
                    latinWords: Number(result.latinWords || 0),
                    numbers: Number(result.numbers || 0)
                });
            }).catch(function (error) {
                if (error && error.name === 'AbortError') {
                    return;
                }

                if (currentSerial === requestSerial) {
                    applyWordStats(fallback);
                }
            });
        }

        function updateStats() {
            scheduled = false;
            imageValue.textContent = String(countImages(preview, textarea));
            tagValue.textContent = String(countTags(tagsInput));

            if (requestTimer) {
                window.clearTimeout(requestTimer);
            }

            requestTimer = window.setTimeout(syncWordStats, 220);
        }

        function scheduleUpdate() {
            if (scheduled) {
                return;
            }

            scheduled = true;
            (window.requestAnimationFrame || window.setTimeout)(updateStats, 16);
        }

        textarea.addEventListener('compositionstart', function () {
            composing = true;
        });

        textarea.addEventListener('compositionend', function () {
            composing = false;
            scheduleUpdate();
        });

        ['input', 'change', 'keyup'].forEach(function (eventName) {
            textarea.addEventListener(eventName, function () {
                if (!composing) {
                    scheduleUpdate();
                }
            });
        });

        textarea.addEventListener('paste', function () {
            window.setTimeout(scheduleUpdate, 0);
        });

        if (tagsInput) {
            ['input', 'change', 'keyup'].forEach(function (eventName) {
                tagsInput.addEventListener(eventName, scheduleUpdate);
            });
        }

        if (window.MutationObserver) {
            if (preview) {
                new MutationObserver(scheduleUpdate).observe(preview, {
                    childList: true,
                    subtree: true,
                    characterData: true
                });
            }

            var tokenList = document.querySelector('.token-input-list');
            if (tokenList) {
                new MutationObserver(scheduleUpdate).observe(tokenList, {
                    childList: true,
                    subtree: true
                });
            }
        }

        scheduleUpdate();
    });
})();
