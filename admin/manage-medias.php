<?php
include 'common.php';
if (!$user->pass('editor', true) && !$user->pass('administrator', true)) gh_error('权限不足');

include 'header.php';
include 'menu.php';
?>

<link rel="stylesheet" href="css/media-common.css">
<style>

/* Filter box */
.filter-box { background:#fff;border:1px solid #efefef;padding:10px;border-radius:2px; }
.filter-title { font-weight:600;margin-bottom:6px; }

/* list */
.dir-search { width:100%; padding:7px; margin-bottom:8px; border:1px solid #eee; border-radius:2px; font-size:13px; }
.dir-list { max-height:420px; overflow:auto; margin:0; padding:0; list-style:none; }
.dir-list li { padding:6px 8px; cursor:pointer; border-radius:2px; color:#444; }
.dir-list li:hover { background:#f5f5f5; }
.dir-list li.active { background:#f0f8ff; color:#1f6fb2; font-weight:600; }
.manager-modal-inner { background:#F6F6F3; padding:5px; border-radius:2px; width:860px; max-width:95%; height:600px; max-height:82vh; display:flex; flex-direction:column; align-items:center; justify-content:center; position:relative; }

.gallery-thumb { cursor: pointer; }
.manager-modal-caption { margin-top:8px; font-size:13px; color:#666; text-align:center; }

/* 鼠标悬浮样式 */
.gallery-item .thumb-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 140px; /* 与缩略图高度一致 */
    background-color: rgba(100, 100, 100, 0.5); /* 灰色半透明背景 */
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0; /* 默认隐藏 */
    transition: opacity 0.3s ease; /* 平滑淡入淡出效果 */
    cursor: pointer;
}
/* 预览文字样式 */
.thumb-overlay::after {
    content: "预览";
    font-size: 15px;
    color: #ffffff; /* 白色文字 */
    font-weight: 500;
    padding: 6px 12px;
    border-radius: 4px;
    background-color: rgba(70, 70, 70, 0.9); /* 文字背景增强对比 */
    transition: transform 0.3s ease;
}
/* 鼠标悬停效果 */
.gallery-item:hover .thumb-overlay {
    opacity: 1;
}


/* upload */
#manager-upload-area { padding:8px; border:1px dashed #e6e6e6; border-radius:2px; text-align:center; color:#666; cursor:pointer; background:transparent; }
#manager-upload-area.drag { background:#fbfbfb; border-color:#dcdcdc; }

/* modal - uniform size, centered */
.manager-modal-viewport { width:100%; height:100%; display:flex; align-items:center; justify-content:center; overflow:hidden; }
.manager-modal-viewport img { max-width:100%; max-height:100%; object-fit:contain; border-radius:2px; }

/* pagination (minimal styles) */
.gallery-pagination { padding:10px 0px; }

@media (max-width:900px) {
    #media-manager {
        flex-direction: column;
    }

    .gallery-left, .gallery-right {
        width: 100%;
    }

    .dir-list { max-height: 130px; }

    /* modal adjustments for easier viewing on phones */
    .manager-modal-inner {
        width: 98%;
        height: 80vh;
        max-height: 98vh;
        padding: 8px;
        box-sizing: border-box;
    }

    /* reduce thumbnail default height a bit for narrow screens */
    .gallery-thumb { height: 120px; object-fit:cover; }

    /* keep pagination compact on small screens */
    .gallery-pagination {
        flex-wrap: nowrap;
    }

    .gallery-pagination label,
    .gallery-pagination input,
    .gallery-pagination button,
    .gallery-pagination .page-info {
        white-space: nowrap;
        flex: 0 0 auto;
    }
    
    /* slightly smaller pagination controls on small screens */
    .gallery-pagination input { width: 36px; }
    .gallery-pagination .btn { padding: 6px 8px; font-size: 13px; }
}
</style>

<main class="main">
    <div class="body container">
        <?php include 'page-title.php'; ?>
        <span id="gallery-count" style="font-size:13px;font-weight:600;color:#666;">（共0张）</span>
        <div id="media-manager" style="display:flex;gap:14px;align-items:stretch;margin-top:10px">
            <div class="gallery-left">
                <div class="filter-box">
                    <div class="filter-title">目录筛选</div>
                    <input id="dir-search" class="dir-search" type="text" placeholder="筛选目录...">
                    <ul id="dir-list" class="dir-list" style="margin-top:6px;"></ul>
                    <div style="margin-top:10px;">
                        <div class="filter-title">上传</div>
                        <div id="manager-upload-area" class="upload-area" data-url="<?php echo $options->adminUrl('github-api.php?action=upload'); ?>"><?php _e('拖放或点击上传'); ?></div>
                    </div>
                </div>
            </div>

            <div class="gallery-right" style="flex:1; position: relative;">
                <div id="gallery" class="gallery-grid" aria-live="polite"></div>
                <div id="gallery-empty" class="common-empty" style="display:none;padding:20px;text-align:center;color:#666;">没有图片</div>
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
</main>

<!-- modal -->
<div id="manager-modal" class="common-modal" role="dialog" aria-hidden="true">
    <div class="manager-modal-inner" role="document">
        <button id="manager-modal-close" class="btn primary" style="position:absolute; right:8px; top:8px;" aria-label="关闭">关闭</button>
        <div class="manager-modal-viewport" id="manager-modal-content"></div>
        <div id="manager-modal-caption" class="manager-modal-caption"></div>
    </div>
</div>

<?php
include 'copyright.php';
include 'common-js.php';
include 'footer.php';
?>

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
(function($){
    function pageGo() {
        var v = parseInt($('#page-input').val(), 10) || 1;
        var totalPages = Math.max(1, Math.ceil(pageState.totalItems / pageState.pageSize));
        if (v < 1) v = 1;
        if (v > totalPages) v = totalPages;
        pageState.currentPage = v;
        renderGallery(filteredImages);
        var vp = $('#gallery')[0];
        if (vp) vp.scrollTop = 0;
    }
    $(function(){
        if ($('#media-manager').length === 0) return;
        
        loadAllImages();

        // 目录选择
        $('#dir-list').on('click', '.dir-item', function(e){
            e.preventDefault();
            $('#dir-list .dir-item').removeClass('active');
            $(this).addClass('active');
            pageState.currentPage = 1;
            const dir = $(this).attr('data-dir') || '';
            if (dir === '') {
                filteredImages = allImages;
            } else {
                const prefix = dir + '/';
                filteredImages = allImages.filter(function(item) {
                    return (item.path || '').indexOf(dir + '/') === 0;
                });
            }
            renderGallery(filteredImages);
        });

        // 目录搜索
        $('#dir-search').on('input', function(){
            const q = $(this).val().toLowerCase();
            $('#dir-list .dir-item').each(function(){
                const txt = $(this).text().toLowerCase();
                $(this).toggle(txt.indexOf(q) !== -1);
            });
        });

        // 上传区域
        const $area = $('#manager-upload-area');
        $area.on('dragover', function(e){
            e.preventDefault();
            $area.addClass('drag');
        });
        $area.on('dragleave drop', function(e){
            e.preventDefault();
            $area.removeClass('drag');
        });
        $area.on('drop', function(e){
            e.preventDefault();
            uploadFile(e.originalEvent.dataTransfer.files[0]);
        });
        $area.on('click', function(){
            const $f = $('<input type="file" accept="image/*" />');
            $f.on('change', function(){
                uploadFile(this.files[0], function(){
                    loadAllImages();
                });
            });
            $f.trigger('click');
        });

        // 页面控制
        $('#page-prev').on('click', function(){
            if (pageState.currentPage > 1) {
                pageState.currentPage--;
                renderGallery(filteredImages);
                // 滚动到顶部
                var vp = $('#gallery')[0];
                if (vp) vp.scrollTop = 0;
            }
        });
        $('#page-next').on('click', function(){
            var totalPages = Math.ceil(pageState.totalItems / pageState.pageSize);
            if (pageState.currentPage < totalPages) {
                pageState.currentPage++;
                renderGallery(filteredImages);
                var vp = $('#gallery')[0];
                if (vp) vp.scrollTop = 0;
            }
        });
        $('#page-go').on('click', function(){
            pageGo();
        });
        $('#page-input').on('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                pageGo();
            }
        });

        // gallery actions
        $('#gallery').on('click', '.thumb-overlay', function(e){
            e.preventDefault();
            const name = $(this).attr('data-name');
            const size = $(this).attr('data-size');
            const url = $(this).attr('data-url');
            openModalWithImage(url, name, size);
        });
        $('#gallery').on('click', '.copy-link', function(e) {
            e.preventDefault();
            const url = decodeURIComponent($(this).attr('data-url'));
            if (!url) return;

            // 创建隐藏的文本框用于复制
            const textarea = document.createElement('textarea');
            textarea.value = url;
            textarea.style.position = 'fixed'; // 避免滚动影响
            document.body.appendChild(textarea);
            
            // 选中并复制
            textarea.select();
            document.execCommand('copy'); // 此方法依赖用户点击事件，100%有效
            
            // 清理
            document.body.removeChild(textarea);
            showNotice('链接已复制', 'success');
        });
        $('#gallery').on('click', '.move-link', function(e){
            e.preventDefault();
            const src = decodeURIComponent($(this).attr('data-path') || '');
            moveFile(src);
        });
        $('#gallery').on('click', '.delete-link', function(e){
            e.preventDefault();
            const path = decodeURIComponent($(this).attr('data-path') || '');
            deleteImage(path);
        });

        // modal
        $('#manager-modal-close').on('click', function(){ closeModal(); });
        $('#manager-modal').on('click', function(e){ if (e.target === this) closeModal(); });
    });
})(jQuery);
</script>