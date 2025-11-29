function apiGet(action, data) {
    data = data || {};
    data.action = action;
    return $.getJSON(apiUrl, data);
}

function apiPost(action, data) {
    data = data || {};
    data.action = action;
    return $.ajax({ url: apiUrl, method: 'POST', data: data, dataType: 'json' });
}

function escapeHtml(s){
    return String(s).replace(/[&<>"'\/]/g, function(c){
        return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;','/':'&#x2F;'}[c];
    });
}

function escapeAttr(s){
    return String(s).replace(/"/g, '&quot;');
}

function isPhone() {
    const isMobileDevice = /Android|iPhone|iOS|Windows Phone|Mobile/i.test(navigator.userAgent);
    const screenWidth = window.screen.width;
    // 手机屏幕宽度通常 < 900px（可根据需求调整阈值）
    return isMobileDevice && screenWidth < 900;
}

function showNotice(message, type) {
    type = type || 'success';
    var $msg = $('<div class="message popup ' + type + '"><ul><li></li></ul></div>');
    $msg.find('li').text(message);
    var $head = $('.typecho-head-nav');
    if ($head.length) {
        $msg.insertAfter($head);
        var offset = $head.outerHeight() || 0;
        $msg.css({ position: 'absolute', top: offset });
        $(window).on('scroll.typechoMsg', function(){
            if ($(window).scrollTop() >= offset) $msg.css({ position: 'fixed', top: 0 });
            else $msg.css({ position: 'absolute', top: offset });
        });
    } else {
        $msg.prependTo(document.body);
    }
    $msg.hide().slideDown(180);
    setTimeout(function(){ $msg.fadeOut(300, function(){ $(this).remove(); $(window).off('scroll.typechoMsg'); }); }, 3500);
}

function renderGallery(images) {
    pageState.totalItems = (images && images.length) ? images.length : 0;
    const $g = $('#gallery').empty();
    if (!images || images.length === 0) {
        $('#gallery-empty').show();
        $('#gallery-pagination').hide();
        return;
    }
    $('#gallery-empty').hide();
    $('#gallery-count').text('（共' + pageState.totalItems + '张）');

    // 分页
    var startIndex = (pageState.currentPage - 1) * pageState.pageSize;
    var endIndex = Math.min(startIndex + pageState.pageSize, pageState.totalItems);
    var pageFiles = images.slice(startIndex, endIndex);

    pageFiles.forEach(function(img){
        const $item = $('<div class="gallery-item"></div>');

        // 图片属性
        const $img = $('<img class="gallery-thumb" loading="lazy">')
            .attr('src', img.url)
            .attr('alt', img.name);
        $item.append($img);

        // 预览覆盖层
        if ($('#media-manager').length) {
            $item.append('<div class="thumb-overlay" data-url="'+ escapeAttr(img.url) +'" data-name="'+ escapeAttr(img.name) +'" data-size="'+ (img.size !== undefined && img.size !== null ? img.size : '') +'" ></div>');
        }
        
        // 文件名显示
        var sizeHtml = '';
        if (img.size !== undefined && img.size !== null) {
            sizeHtml = '<div class="file-size">' + escapeHtml(formatBytes(img.size)) + '</div>';
        } else {
            sizeHtml = '<div class="file-size"></div>';
        }
        var nameHtml = '<div class="file-name">' + escapeHtml(img.name) + '</div>';
        $item.append('<div class="gallery-meta"></div>');
        $item.find('.gallery-meta').append(nameHtml).append(sizeHtml);

        // 操作按钮
        const $actions = $('<div class="gallery-actions"></div>');
        if ($('#media-manager').length) {
            $actions.append('<a href="#" class="copy-link" data-url="' + escapeAttr(img.url) + '">复制链接</a>&nbsp;&nbsp;');
        } else if ($('#media-box-modal').length) {
            $actions.append('<a href="#" class="insert-link" data-url="' + escapeAttr(img.url) + '">插入</a>&nbsp;&nbsp;');
        }
        $actions.append('<a href="#" class="move-link" data-path="'+ escapeAttr(img.full_path) +'">移动</a>&nbsp;&nbsp;');
        $actions.append('<a href="#" class="delete-link" data-path="'+ escapeAttr(img.full_path) +'">删除</a>');
        $item.append($actions);
        $g.append($item);
    });
    updatePagination();
}

function renderDirs(list) {
    if ($('#media-manager').length) {
        const $ul = $('#dir-list').empty();
        $ul.append('<li class="dir-item active" data-dir="">全部</li>');
        (list || []).forEach(function(d){
            $('<li class="dir-item"></li>').text(d).attr('data-dir', d).appendTo($ul);
        });
    } else if ($('#media-box-modal').length) {
        var $sel = $('#mediabox-dir-select').empty();
        $sel.append('<option value="">全部</option>');
        (list || []).forEach(function (d) {
            $sel.append('<option value="' + escapeAttr(d) + '">' + escapeHtml(d) + '</option>');
        });
    }
}

function updatePagination(){
    if (pageState.totalItems <= pageState.pageSize) {
        $('#gallery-pagination').hide();
        return;
    }
    var totalPages = Math.ceil(pageState.totalItems / pageState.pageSize);
    $('#page-info').text('第 ' + pageState.currentPage + ' / ' + totalPages + ' 页');
    $('#page-input').val(pageState.currentPage);
    $('#gallery-pagination').show();
    $('#page-prev').prop('disabled', pageState.currentPage <= 1);
    $('#page-next').prop('disabled', pageState.currentPage >= totalPages);
}

function loadAllImages() {
    $('#gallery').html('<div class="common-empty">加载图片中…</div>');
    apiGet('list_all').done(function(res){
        if (!res || !res.ok) {
            $('#gallery').empty();
            $('#gallery-empty').show().text(res && res.message ? res.message : '加载失败');
            $('#gallery-pagination').hide();
            return;
        }
        allImages = res.images || [];
        filteredImages = allImages;
        renderGallery(filteredImages);
        renderDirs(res.dirs || []);
    }).fail(function(){ $('#gallery').empty(); $('#gallery-empty').show().text('加载失败'); $('#gallery-pagination').hide(); });
}

function moveFile(src) {
    var dst = prompt('输入目标路径（还可以实现重命名文件/目录），例如 targetdir/' + src.split('/').pop(), src);
    if (!dst) return;

    var loadingHtml = '<div class="process-loading">'
        + '<i class="i-loading"></i>'
        + '<span class="loading-text">移动中...</span>'
        + '</div>';
    $('body').append(loadingHtml);

    apiPost('move', { src: src, dst: dst })
        .done(function(res){
            if (!res || !res.ok) {
                showNotice(res && res.message ? res.message : '移动失败','error');
                return;
            }
            showNotice(res.message || '移动成功','success');
            loadAllImages();
        })
        .fail(function(xhr){ showNotice((xhr.responseJSON && xhr.responseJSON.message) || '移动失败','error'); })
        .complete(function(){
            $('.process-loading').remove();
        });
}

function deleteImage(path) {
    if (!path) { showNotice('删除路径为空','error'); return; }
    if (!confirm('确认删除 ' + path + ' ?')) return;

    var loadingHtml = '<div class="process-loading">'
        + '<i class="i-loading"></i>'
        + '<span class="loading-text">删除中...</span>'
        + '</div>';
    $('body').append(loadingHtml);

    apiPost('delete', { path: path })
        .done(function(res){
            if (!res || !res.ok) {
                showNotice(res && res.message ? res.message : '删除失败','error');
                return;
            }
            showNotice(res.message || '删除成功','success');
            loadAllImages();
        })
        .fail(function(xhr){ showNotice((xhr.responseJSON && xhr.responseJSON.message) || '删除失败','error'); })
        .complete(function(){
            $('.process-loading').remove();
        });
}

function uploadFile(file, successCallback) {
    if (!file) return;

    // 显示上传中提示
    var loadingHtml = '<div class="process-loading">'
        + '<i class="i-loading"></i>'
        + '<span class="loading-text" >上传中...</span>'
        + '</div>';
    $('body').append(loadingHtml);
    
    // 获取当前选中的目录（默认为空）
    const selectedDir = $('#dir-list .dir-item.active').attr('data-dir') || '';
    
    const fd = new FormData();
    fd.append('action', 'upload');
    fd.append('path', selectedDir);
    fd.append('file', file);
    // 如果来自编辑器
    if ($('#media-box-modal').length) {
        fd.append('isFromEditor', '1');
    }

    $.ajax({
        url: apiUrl,
        data: fd,
        type: 'POST',
        dataType: 'json',
        contentType: false,
        processData: false,
        success: function(res) {
            if (!res || !res.ok) { 
                showNotice(res && res.message ? res.message : '上传失败', 'error'); 
                return;
            }
            showNotice(res.message || '上传成功', 'success');
            // 回调函数，用于两个上传地方的不同处理
            if (typeof successCallback === 'function') {
                successCallback(res);
            }
        },
        error: function(xhr) {
            var msg = '上传失败';
            try { 
                msg = (xhr.responseJSON && xhr.responseJSON.message) ? 
                    xhr.responseJSON.message : 
                    (xhr.responseText ? xhr.responseText.replace(/<\/?[^>]+(>|$)/g, '').trim().split('\n')[0] : msg); 
            } catch(e){}
            showNotice(msg, 'error');
            console.error('UPLOAD failed', xhr);
        },
        // 无论成功或失败，均移除上传中提示
        complete: function() {
            $('.process-loading').remove();
        }
    });
}

function openModalWithImage(url, name, size) {
    $('#manager-modal-content').html('<img src="'+ url +'" alt="'+ escapeAttr(name) +'" />');
    var captionText = name || '';
    if (size !== undefined && size !== null && size !== '') {
        captionText += ' — ' + formatBytes(Number(size));
    }
    $('#manager-modal-caption').text(captionText);
    $('#manager-modal-caption').append(
        $('<div>').css({marginTop:'8px',fontSize:'13px',color:'#666'}).text(decodeURIComponent(url))
    );
    $('#manager-modal').addClass('show').attr('aria-hidden','false');
    $(document).on('keydown.modal', function(e){
        if (e.key === 'Escape' || e.key === 'Esc') closeModal();
    });
}

function openModal() {
    loadAllImages();
    $('#media-box-modal').addClass('show').attr('aria-hidden', 'false');
    $(document).on('keydown.modal', function(e){
        if (e.key === 'Escape' || e.key === 'Esc') closeModal();
    });
}

function closeModal() {
    const activeElement = document.activeElement; // 当前持有焦点的元素（此处是 #manager-modal-close）
    activeElement.blur(); // 让模态框内的聚焦元素失焦
    $(document).off('keydown.modal');
    if ($('#media-manager').length) {
        $('#manager-modal').removeClass('show').attr('aria-hidden','true');
        $('#manager-modal-content').empty();
        $('#manager-modal-caption').text('');
    } else if ($('#media-box-modal').length) {
        $('#media-box-modal').removeClass('show').attr('aria-hidden', 'true');
    }
}

function formatBytes(bytes) {
    if (bytes === undefined || bytes === null) return '';
    var sizes = ['B','KB','MB','GB','TB'];
    if (bytes === 0) return '0 B';
    var i = parseInt(Math.floor(Math.log(bytes) / Math.log(1024)), 10);
    if (i === 0) return bytes + ' ' + sizes[i];
    return (bytes / Math.pow(1024, i)).toFixed(1) + ' ' + sizes[i];
}