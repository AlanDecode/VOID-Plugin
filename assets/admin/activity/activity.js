window.loadMoreActivity = function () {
    if ($('#votes').hasClass('nomore')) {
        $('button.loadmore').html('没有了～');
        return;
    }

    $('button.loadmore').html('加载中...');

    var api = window.queryActivityUrl;
    if (typeof($('#votes').attr('data-stamp')) != 'undefined' && $('#votes').attr('data-stamp') != '-1')
        api += '&older_than=' + String($('#votes').attr('data-stamp'));

    var from = {
        'comments': '评论',
        'contents': '文章'
    };

    $.getJSON(api, function (data) {
        $('#votes').attr('data-stamp', String(data.stamp));
        if (data.stamp == -1) {
            $('button.loadmore').html('没有了～');
            $('#votes').addClass('nomore');
        } else {
            $('button.loadmore').html('加载更多');
        }

        $.each(data.data, function (i, item) {
            var type = item.type == 'up' ? '赞' : '踩';
            var label = from[item.from] || '内容';
            var $item = $('<li></li>').addClass('vote').addClass(item.from).addClass(item.type);
            var $inner = $('<div></div>').addClass('vote-inner');
            var $meta = $('<span></span>').addClass('meta');
            var $misc = $('<span></span>').addClass('misc');
            var browserHtml = typeof item.browser === 'string' ? item.browser : '';

            $inner.append(document.createTextNode(label + '「'));
            $inner.append(
                $('<a></a>')
                    .attr('href', item.url)
                    .attr('target', '_blank')
                    .text(item.content)
            );
            $inner.append(document.createTextNode('」收获了一个' + type + '。'));

            $misc.append(document.createTextNode((item.location || '未知') + ', '));
            $misc.append(browserHtml);
            $misc.append(document.createTextNode(', ' + (item.os || 'Unknown')));

            $meta.append($misc);
            $meta.append($('<time></time>').text(item.created_format));
            $inner.append($meta);
            $item.append($inner);

            $('#votes').append($item);
        });
    })
}

window.loadMoreActivity();
