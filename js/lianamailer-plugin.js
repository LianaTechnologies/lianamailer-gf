function enableElement($elem) {
	$elem.prop('disabled', false);
}
function disableElement($elem) {
	$elem.prop('disabled', true);
}

jQuery( document ).ready( function($) {

	var $enableCb = $('#tab_lianaMailerSettings input#lianamailer_enabled');
	var $siteSelect = $('#tab_lianaMailerSettings select#lianamailer_site');
	var $mailingListSelect = $('#tab_lianaMailerSettings select#lianamailer_mailing_list');
	var $consentSelect = $('#tab_lianaMailerSettings select#lianamailer_consent');

	if($enableCb.length) {
		toggleLianaMailerPlugin();
	}

	function toggleLianaMailerPlugin() {

		disableElement($mailingListSelect);
		disableElement($consentSelect);

		if($enableCb.is(':checked')) {
			$siteSelect.removeClass('disabled');
			// if mailing lists found for the selected site
			if($mailingListSelect.find("option:gt(0)").length > 0) {
				enableElement($mailingListSelect);
			}
			// if consents found for the selected site, enable the select
			if($consentSelect.find("option:gt(0)").length > 0) {
				enableElement($consentSelect);
			}

		}
		else {
			disableElement($siteSelect);
			disableElement($mailingListSelect);
			disableElement($consentSelect);
		}
	}

	if($enableCb.is(':checked')) {
		console.log('Plugin is enabled!');
		$mailingListSelect.attr('required', 'required');
	}
	else {
		$mailingListSelect.removeAttr('required');
	}

	$enableCb.change(function() {
		if($(this).is(':checked')) {
			enableElement($siteSelect);
			// if mailing lists found for the selected site
			if($mailingListSelect.find("option:gt(0)").length > 0) {
				enableElement($mailingListSelect);
			}
			// if consents found for the selected site, enable the select
			if($consentSelect.find("option:gt(0)").length > 0) {
				enableElement($consentSelect);
			}
			$mailingListSelect.attr('required', 'required');
		}
		else {
			disableElement($siteSelect);
			disableElement($mailingListSelect);
			disableElement($consentSelect);
			$mailingListSelect.removeAttr('required');
		}
	});

	$siteSelect.change(function() {
		var siteValue = $(this).val();

		disableElement($mailingListSelect);
		disableElement($consentSelect);

		$mailingListSelect.find("option:gt(0)").remove();
		$consentSelect.find("option:gt(0)").remove();

		if(!siteValue) {
			$mailingListSelect.addClass('disabled').find("option:gt(0)").remove();
			$consentSelect.addClass('disabled').find("option:gt(0)").remove();
		}
		else {

			let params = {
				url: lianaMailerConnection.url,
				method: 'POST',
				dataType: 'json',
				data: {
					'action': 'getSiteDataForGFSettings',
					'site': siteValue,
					'id': lianaMailerConnection.form_id
				}
			};

			$.ajax(params).done(function( data ) {
				console.log('Got this from the server: ', data);

				var lists = data.lists;
				var consents = data.consents;

				if(lists.length) {
					$mailingListSelect.find("option:gt(0)").remove();
					var options = [];
					$.each(lists, function( index, listData ) {
						var opt = document.createElement('option');
						opt.value = listData.id;
						opt.text = listData.name;
						options.push(opt);
					});
					$mailingListSelect.append(options);
					$mailingListSelect.removeClass('disabled');
					enableElement($mailingListSelect);
				}

				if(consents.length) {
					$consentSelect.find("option:gt(0)").remove();
					var options = [];
					$.each(consents, function( index, consentData ) {
						var opt = document.createElement('option');
						opt.value = consentData.consent_id;
						opt.text = consentData.name;
						options.push(opt);
					});
					$consentSelect.append(options);
					$consentSelect.removeClass('disabled');
					enableElement($consentSelect);
				}

			  });
		}
	});

});

// Setting LianaMailer settings
jQuery(document).on('gform_load_lianamailer_form_settings', function(evt, form) {
	if(form.lianamailer !== undefined) {
		var enabled = form.lianamailer.lianamailer_enabled || '';
		var site = form.lianamailer.lianamailer_site || '';
		var mailingList = form.lianamailer.lianamailer_mailing_list || '';
		var consent = form.lianamailer.lianamailer_consent || '';

		jQuery("input#lianamailer_enabled").prop('checked', parseInt(enabled));
		jQuery("select#lianamailer_site").val(site).trigger('change');
		jQuery("select#lianamailer_mailing_list").val(mailingList);
		jQuery("select#lianamailer_consent").val(consent);
	}
});

var LianaMailerProperties = {};
// Setting LianaMailer field settings
jQuery(document).on('gform_load_field_settings', function(evt, field) {

	if(field.type != 'lianamailer') {
		return;
	}

	if(field.lianamailer_properties == undefined) {
		console.log('No lianamailer properties mapped!');
		return;
	}

	var $requiredCB = jQuery('#field_required');
	$requiredCB.prop('checked', true);
	field.isRequired = true;

	var properties = field.lianamailer_properties;
	jQuery.each(properties, function( handle, value ) {
		var $select = jQuery('.lianamailer_properties_setting select[data-handle="'+handle+'"]');
		// check if form field still exists in form
		if($select.find('option[value="'+value+'"]').length) {
			$select.val(value);
		}
		// if select not found, probably property is deleted from LM´s site or site has been changed
		if(!$select.length) {
			delete properties[handle];
		}
	});
	LianaMailerProperties = properties;
});

function SetLianaMailerProperty($elem) {
	var key = $elem.data('handle');
	var value = $elem.val();

	LianaMailerProperties[key] = value;

	SetFieldProperty('lianamailer_properties', LianaMailerProperties);
}

var deletedLianamailerField = false;
function StartAddField_Lianamailer(type, element) {
	deletedLianamailerField = false;

	if(jQuery(element).hasClass('disabled')) {
		return;
	}
	StartAddField(type);
	jQuery('div#add_fields_menu button.lianamailer').addClass('disabled');
}

function DeleteField_Lianamailer(element) {
	deletedLianamailerField = true;
	DeleteField(element);
}

jQuery( document ).on( 'gform_field_deleted', function ( event, form, fieldId ) {
	if(deletedLianamailerField) {
		jQuery('div#add_fields_menu button.lianamailer').removeClass('disabled');
	}
} );