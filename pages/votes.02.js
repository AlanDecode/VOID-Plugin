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

    var template =  '<li class="vote {fr} {cl}">' + 
                        '<div class="vote-inner">{from}「<a href="{url}" target="_blank">{content}</a>」收获了一个{type}。' + 
                            '<span class="meta">' + 
                                '<span class="misc">{misc}</span>' +
                                '<time>{date}</time></span>' + 
                        '</div>' + 
                    '</li>';

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

            var html = template
                .replace('{cl}', item.type)
                .replace('{fr}', item.from)
                .replace('{type}', type)
                .replace('{from}', from[item.from])
                .replace('{url}', item.url)
                .replace('{content}', item.content)
                .replace('{misc}', item.location + ', ' + item.browser + ', ' + item.os)
                .replace('{date}', item.created_format);
            
            $('#votes').append(html);
        });
    })
}

window.loadMoreActivity();