<?php
include_once 'common.php';
if (!$user->pass('editor', true) && !$user->pass('administrator', true)) gh_error('权限不足');
?>
<link rel="stylesheet" href="css/media-common.css">
<style>
.box-modal-inner {
    background: #F6F6F3;
    padding: 5px;
    border-radius: 2px;
    width: 1000px;
    max-width: 95%;
    height: 700px;
    max-height: 82vh;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    position: relative;
}

.gallery-main {
    width: 100%;
    height: 100%;
    display: block;
    overflow: auto;
    padding: 12px;
    box-sizing: border-box;
}

/* title bar layout for dropdown filter */
.mediabox-title {
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 8px 12px;
    border-bottom: 1px solid #f0f0f0;
    box-sizing: border-box;
    overflow: visible;
    /* fix dropdown clipping */
}

.mediabox-title-left {
    display: flex;
    align-items: center;
    gap: 12px;
}

.mediabox-title-right {
    display: flex;
    align-items: center;
    gap: 8px;
}

.gallery-pagination {
    padding: 8px 8px 1px 12px;
}

@media (max-width:900px) {
    .box-modal-inner {
        width: 100%;
        height: 80vh;
    }

    .gallery-thumb {
        height: 120px;
    }

    .gallery-main {
        padding: 8px;
    }

    .mediabox-title {
        flex-wrap: wrap;
        gap: 8px;
    }

    .gallery-pagination {
        flex-wrap: nowrap;
        padding: 8px 8px 1px 6px;
    }

    .gallery-pagination label,
    .gallery-pagination input,
    .gallery-pagination button,
    .gallery-pagination .page-info {
        white-space: nowrap;
        flex: 0 0 auto;
    }

    /* make controls a bit smaller and compact on phones */
    .gallery-pagination input { width: 36px; }
    .gallery-pagination .btn { padding: 6px 8px; font-size: 13px; }
}
</style>

<!-- Modal -->
<div id="media-box-modal" class="common-modal" role="dialog" aria-hidden="true">
    <div class="box-modal-inner" role="document">
        <div class="mediabox-title">
            <div class="mediabox-title-left">
                <div style="font-weight:600;font-size:18px;">素材箱<span id="gallery-count"
                        style="font-size:13px;color:#666;margin-left:8px;">（共0张）</span></div>
            </div>

            <div class="mediabox-title-right">
                <select id="mediabox-dir-select">
                    <option value="">全部</option>
                </select>
                <button id="mediabox-refresh" class="btn" style="margin-left:8px;">刷新</button>
                <button id="mediabox-close" class="btn primary" style="margin-left:8px;">关闭</button>
            </div>
        </div>

        <div class="gallery-main">
            <div id="gallery" class="gallery-grid" aria-live="polite"></div>
            <div id="gallery-empty" class="common-empty" style="display:none;padding:20px;text-align:center;color:#666;">
                没有图片
            </div>
        </div>

        <div id="gallery-pagination" class="gallery-pagination" style="display:none;">
            <span class="page-info" id="page-info"></span>
            <label style="font-size:13px;color:#666;">第</label>
            <input id="page-input" type="text" value="1" style="width:40px; text-align:center;" />
            <label style="font-size:13px;color:#666;">页</label>
            <button id="page-go" class="btn">跳转</button>
            <button id="page-prev" class="btn">上一页</button>
            <button id="page-next" class="btn">下一页</button>
        </div>
    </div>
</div>
</div>

<script src="js/media-common.js"></script>
<script>
const apiUrl = '<?php echo $options->adminUrl('github-api.php'); ?>';
var pageState = {
    currentPage: 1,
    pageSize: 20,
    totalItems: 0
};
let allImages = [];
let filteredImages = [];

(function ($) {
    function handleInsert(url) {
        Typecho.insertFileToEditor(url);
        closeModal();
    }

    function pageGo() {
        var v = parseInt($('#page-input').val(), 10) || 1;
        var totalPages = Math.max(1, Math.ceil(pageState.totalItems / pageState.pageSize));
        if (v < 1) v = 1;
        if (v > totalPages) v = totalPages;
        pageState.currentPage = v;
        renderGallery(filteredImages);
        var vp = $('.gallery-main')[0];
        if (vp) vp.scrollTop = 0;
    }

    $(function () {
        // 刷新按钮
        $('#mediabox-refresh').on('click', function (e) {
            e.preventDefault();
            pageState.currentPage = 1;
            loadAllImages();
        });

        // 目录选择
        $('#mediabox-dir-select').on('change', function () {
            pageState.currentPage = 1;
            const dir = $(this).val() || '';
            if (dir === '') {
                filteredImages = allImages;
            } else {
                filteredImages = allImages.filter(function(item) {
                    return (item.path || '').indexOf(dir + '/') === 0;
                });
            }
            renderGallery(filteredImages);
        });

        // 页面控制
        $('#page-prev').on('click', function () {
            if (pageState.currentPage > 1) {
                pageState.currentPage--;
                renderGallery(filteredImages);
                var vp = $('.gallery-main')[0];
                if (vp) vp.scrollTop = 0;
            }
        });
        $('#page-next').on('click', function () {
            var totalPages = Math.ceil(pageState.totalItems / pageState.pageSize);
            if (pageState.currentPage < totalPages) {
                pageState.currentPage++;
                renderGallery(filteredImages);
                var vp = $('.gallery-main')[0];
                if (vp) vp.scrollTop = 0;
            }
        });
        $('#page-go').on('click', function () {
            pageGo();
        });
        $('#page-input').on('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                pageGo();
            }
        });

        // gallery actions
        $('#gallery').on('click', '.insert-link', function (e) {
            e.preventDefault();
            var url = decodeURIComponent($(this).attr('data-url'));
            handleInsert(url);
        });
        $('#gallery').on('click', '.move-link', function (e) {
            e.preventDefault();
            const src = decodeURIComponent($(this).attr('data-path') || '');
            moveFile(src);
        });
        $('#gallery').on('click', '.delete-link', function (e) {
            e.preventDefault();
            const path = decodeURIComponent($(this).attr('data-path') || '');
            deleteImage(path);
        });

        $('#mediabox-close').on('click', function () { closeModal(); });
        $('#media-box-modal').on('click', function (e) { if (e.target === this) closeModal(); });
    });
})(jQuery);
</script>