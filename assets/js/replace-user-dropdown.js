window.WDS_Replace_User_Dropdown = window.WDS_Replace_User_Dropdown || {};

(function( window, document, $, app, undefined ) {
	'use strict';

	app.l10n = window.wds_rud_config || {};

	app.select2Init = function() {
		app.$select = $( document.getElementById( 'wds-user-search' ) );

		app.$select.select2({
			placeholder : app.l10n.placeholder_text,
			minimumInputLength : 3,
			allowClear: true,
			width: '60%',
			ajax : {
				cache : false,
				url : ajaxurl,
				dataType : 'json',
				data : function (term, page) {
					return {
						q : term,
						action : app.l10n.ajax_callback,
						nonce : app.l10n.nonce,
					};
				},
				results : app.select2Data
			},
			initSelection : function( element, callback ) {
				var id = $('#wds-user-search').val();

				if ( '0' !== id ) {
					return $.ajax({
						url : ajaxurl,
						dataType : 'json',
						data : {
							q : app.l10n.display_name,
							id : id,
							action : app.l10n.ajax_callback,
							nonce : app.l10n.nonce,
						},
					}).done(function( data ) {
						if ( data.success ) {
							var results = {
								id : data.data[0].id,
								text : data.data[0].text
							};
							callback( results );
						} else {
							var results = {
								id : 0,
								text : app.l10n.placeholder_text
							};
							callback( results );
						}
					});
				} else {
					var results = {
						id : 0,
						text : app.l10n.placeholder_text
					};
					callback( results );
				}
			}
		});
	};

	app.select2Data = function( ajax_data, page, query ) {
		var items=[];

		$.each( ajax_data.data, function( i, item ) {
			console.log( item );

			
			var new_item = {
				'id' : item.id,
				'text' : item.text
			};

			items.push( new_item );
		});

		return { results: items };
	};

	$( document ).ready( app.select2Init );

	return app;

})( window, document, jQuery, WDS_Replace_User_Dropdown );
