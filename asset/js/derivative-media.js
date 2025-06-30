$(function () {

    // https://stackoverflow.com/a/39906526
    var units = ['bytes', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
    function niceBytes(x) {
        let l = 0, n = parseInt(x, 10) || 0;
        while(n >= 1000 && ++l){
            n = n / 1000;
        }
        return(n.toFixed(n < 10 && l > 0 ? 1 : 0) + ' ' + units[l]);
    }

    $('a.derivative-media:not(.demand-confirmed)').on('click', (e) => {
        const link = $(e.target);
        const url = link.data('url');
        const size = link.data('size');
        const href = link.attr('href');

        if (href && href.length && href !== '#') {
            return true;
        }

        e.stopPropagation();
        e.preventDefault();

        const derivativeList = $(link).closest('.derivative-list');

        // The size may be unknown in case of a unbuild file.
        units = derivativeList.data('text-units') ? derivativeList.data('text-units') : units;
        const niceSize = size ? niceBytes(size) : size;
        const textWarn = size
            ? (derivativeList.data('text-warn-size') ? derivativeList.data('text-warn-size').replace('{size}', niceSize) : `Are you sure to download the file (${niceSize})?`)
            : (derivativeList.data('text-warn') ? derivativeList.data('text-warn') : 'Are you sure to download this file?');
        const textNo = derivativeList.data('text-no') ? derivativeList.data('text-no') : 'No';
        const textYes = derivativeList.data('text-yes') ? derivativeList.data('text-yes') : 'Yes';
        const textQueued = derivativeList.data('text-queued') ? derivativeList.data('text-queued') : 'The file is in queue. Reload the page later.';
        const textOk = derivativeList.data('text-ok') ? derivativeList.data('text-ok') : 'Ok';

        const html=`
<dialog id="derivative-on-demand" class="derivative-dialog">
    <form id="derivative-form" method="dialog" action="#">
        <p>${textWarn}</p>
        <div class="derivative-actions" style="display: flex; justify-content: space-evenly;">
            <button type="button" id="derivative-no" class="derivative-no" value="no" formmethod="dialog">${textNo}</button>
            <button type="button" id="derivative-yes" class="derivative-yes" value="yes" formmethod="dialog">${textYes}</button>
        </div>
    </form>
</dialog>
<dialog id="derivative-queued" class="derivative-dialog">
    <form id="derivative-form-queud" method="dialog" action="#">
        <p>${textQueued}</p>
        <div class="derivative-actions" style="display: flex; justify-content: space-evenly;">
            <button type="button" id="derivative-ok" class="derivative-ok" value="yes" formmethod="dialog">${textOk}</button>
        </div>
    </form>
</dialog>
`;

        if ($('#derivative-on-demand').length) {
           $('#derivative-on-demand').remove();
        }

        $('body').append(html);

        link.attr('href', '#');

        const dialog = document.getElementById('derivative-on-demand');
        const no = document.getElementById('derivative-no');
        const yes = document.getElementById('derivative-yes');

        dialog.showModal();

        no.addEventListener('click', (e) => {
            dialog.close();
            e.preventDefault();
            link.attr('href', '#');
            return false;
        });

        yes.addEventListener('click', () => {
            dialog.close();
            if (!link.hasClass('on-demand')) {
                link.attr('href', url);
                 // Create an element to force direct download
                // link.click();
                var el = document.createElement('a');
                el.setAttribute('href', url);
                el.setAttribute('download', link.attr('download'));
                el.setAttribute('style', 'display: none;');
                document.body.appendChild(el);
                el.click();
                document.body.removeChild(el);
                return true;
            }
            // Js forbids a click to a link, so send via ajax. Anyway, the
            // response should not be sent. Use argument "prepare" to avoid
            // to send response immediatly.
            // link.addClass('demand-confirmed');
            $.get(url, {prepare: 1})
                .fail(function() {
                    // The fail is normal for now.
                    const dialogQueued = document.getElementById('derivative-queued');
                    const ok = document.getElementById('derivative-ok');
                    dialogQueued.showModal();
                    ok.addEventListener('click', () => {
                        dialogQueued.close();
                    });
                })
            ;
            return true;
        });

        return false;
    });

});
