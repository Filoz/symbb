function createBBEditor(parentDiv){
    $(parentDiv).find('.symbb_bbcode_btn').each(function(key, btn){
        $( btn).click(function() {
            var element = $(parentDiv).find('textarea')[0];

            var tagCode = $(btn).data('tag-code');

            if (document.selection) {
                var sel = document.selection.createRange();
                sel.text = tagCode.replace('{0}', sel.text);
            } else if (element.selectionStart || element.selectionStart == '0') {
                var startPos = element.selectionStart;
                var endPos = element.selectionEnd;
                var insert = tagCode.replace('{0}', element.value.substring(startPos, endPos));
                element.value = element.value.substring(0, startPos) + insert + element.value.substring(endPos, element.value.length);
                element.focus();
                element.selectionStart = endPos + insert.length;
            } else {
                element.focus();
                element.value += tagCode.replace('{0}', '');
            }

            $(element).change();
        });
    });
}