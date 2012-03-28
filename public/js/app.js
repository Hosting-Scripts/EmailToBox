$(document).ready(function() {

	function getActiveFolderPair() {
		var hash = location.hash.replace( /^#/, '' );
		if (hash == '') hash = "0";
		return hash;
	}
	
	function slideToggle(trigger, targets) {
		
		for (var i = 0; i < targets.length; ++i) {
			$(targets[i]).hide();
		}
		$(trigger).click(function (e) {
			$(this).toggleClass('active');
			e.preventDefault();
			for (var i = 0; i < targets.length; ++i) {
				$(targets[i]).slideToggle(300).siblings('ul.body').toggleClass('settings-opened');
			}
		});
	}
	
	
	
	// Simple hover tooltip
	function tooltip(trigger) {
		$(trigger).hover(function () {
			var title = ($(this).attr('data-tooltip'));
			var tooltip = $(this).after('<div class="tooltip">'+title+'</div>');
			$('.tooltip').css({ position: 'absolute' });
		}, function () {
			$(this).siblings('.tooltip').fadeOut(300);
		});
	}
	
	
	slideToggle('#settings-toggle', ['#folder-pair-list']);
	tooltip('.sync-error .alert .icon');

	$('#history-paging').click(function (evt) {
		var elm = $(this);
		elm.data('label',elm.text()).text('loading ...').prop('disabled', true);
		var fpId = getActiveFolderPair();
		$.getJSON("/app/fp/"+fpId+"/history/"+$('#history-list').children().length, function (data) {
			var base = $('#saved-attachments');
			if (data.length > 0) {
				$('#history-list').append(
					$('#tmpl-attachment-article').render(data)
				);
			} else {
				elm.hide();
			}
			elm.text(elm.data('label')).prop('disabled', false);
		});
	});

	$('#fp-actions-pause, #fp-actions-resume').click(function (evt) {
		var action = this.id.split('-').pop();
		var elm = $(this);
		elm.data('label', elm.html()).text(action.replace(/e$/,'ing')+" ...");
		var fpId = getActiveFolderPair();
		$.getJSON("/app/fp/"+fpId+"/"+action, function (data) {
			if ('error' in data) {
				alert("Error "+action.replace(/e$/,'ing')+" folder pair.");
			} else {
				elm.html(elm.data('label'));
				$('#fp-actions-pause').toggle((data.paused == 0));
				$('#fp-actions-resume').toggle((data.paused > 0));
			}
		});
	});
	

	$.views.registerHelpers({
		sender: function (obj) {
			return ('name' in obj && obj.name) ? obj.name : obj.email;
		},
		formatBytes: function (numBytes) {
			if (isNaN(parseInt(numBytes))) return '';
			// format file size
			var sizeSuffix = ['B','KB','MB','GB'];
			var size = numBytes;
			var returnVal = {};
			do {
				if (size < 10) {
					// for files with size less than 10, show 1 decimal points in formatted size
					returnVal = {value: (Math.round(size*10) / 10), scale: sizeSuffix.shift(), str: null};
				}
				else {
					returnVal = {value: Math.round(size), scale: sizeSuffix.shift(), str: null};
				}
				size = (size / 1024);
			} while (size >= 1 && sizeSuffix.length > 0);
			
			returnVal.str = returnVal.value + ' ' + returnVal.scale;
			return returnVal.str;			
		},
		formatTime: function (uts) {
			return dateToLocaleFormat(new Date(uts * 1000), '%b %e, %Y [%R]');
		}
	});

	$.views.registerTags({
		get: function (val) {
			return val || this.ifEmpty;
		}
	});

 
	// We use location hash to navigate from a folder pair to another
	$(window).hashchange(function () {
		var hash = getActiveFolderPair();

		// load folder pair information
		$.getJSON("/app/fp/"+hash+"/info/", function (data) {

			var baseElm = $('#fp-email');
			baseElm.find(".folder-name").text(data.contextio.folder_name);
			baseElm.find(".folder-email").text(data.contextio.email);
			$('#fp-box').find(".folder-name").text(data.boxFolder.name);

			$('#fp-actions-pause').toggle((data.paused == 0));
			$('#fp-actions-resume').toggle((data.paused > 0));

			if ($('#saved-attachments').hasClass('empty')) {
				var elm = $('#saved-attachments p.empty');
				elm.find('.folder-name.email').text(data.contextio.folder_name);
				elm.find('.folder-name.box').text(data.boxFolder.name);
			}
		});

		// load folder pair history

		// load folder pair information
		$.getJSON("/app/fp/"+hash+"/history/", function (data) {
			var base = $('#saved-attachments');
			if (data.length > 0) {
				base.removeClass('empty');
				base.find('p.empty').hide();
				$('#history-list').html(
					$('#tmpl-attachment-article').render(data)
				);
				$('#history-paging').show();
			} else {
				base.addClass('empty');
				base.find('p.empty').show();
				base.find('article').remove();
				$('#history-paging').hide();
			}
		});
	});

	// Since the event is only triggered when the hash changes, we need to trigger
	// the event now, to handle the hash the page may have loaded with.
	$(window).hashchange();
});
