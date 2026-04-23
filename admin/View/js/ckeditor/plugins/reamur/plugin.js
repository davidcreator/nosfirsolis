CKEDITOR.plugins.add('reamur', {
	init: function(editor) {
		editor.addCommand('ReamurCMS', {
			exec: function(editor) {
				$('#modal-image').remove();

				$.ajax({
					url: 'index.php?route=common/filemanager&user_token=' + getURLVar('user_token') + '&ckeditor=' + editor.name,
					dataType: 'html',
					success: function(html) {
						$('body').append(html);

						$('#modal-image').modal('show');
					}
				});
			}
		});

		editor.ui.addButton('ReamurCMS', {
			label: 'ReamurCMS',
			command: 'ReamurCMS',
			icon: this.path + 'images/icon.png'
		});
	}
});
