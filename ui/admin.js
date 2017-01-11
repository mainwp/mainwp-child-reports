/* globals confirm, mainwp_wp_stream, ajaxurl */
jQuery(function( $ ) {
        // Shorter timeago strings for English locale
	if ( 'en' === mainwp_wp_stream.locale && 'undefined' !== typeof $.timeago ) {
		$.timeago.settings.strings.seconds = 'seconds';
		$.timeago.settings.strings.minute  = 'a minute';
		$.timeago.settings.strings.hour    = 'an hour';
		$.timeago.settings.strings.hours   = '%d hours';
		$.timeago.settings.strings.month   = 'a month';
		$.timeago.settings.strings.year    = 'a year';
	}


	$( '.mainwp_wp_stream_screen :input.chosen-select' ).each(function( i, el ) {
		var args = {},
			formatResult = function( record, container ) {
				var result = '',
					$elem = $( record.element ),
					icon = '';

				if ( undefined !== record.icon ) {
					icon = record.icon;
				} else if ( undefined !== $elem.attr( 'data-icon' ) ) {
					icon = $elem.data( 'icon' );
				}
				if ( icon ) {
					result += '<img src="' + icon + '" class="wp-stream-select2-icon">';
				}

				result += record.text;

				// Add more info to the container
				container.attr( 'title', $elem.attr( 'title' ) );

				return result;
			};

		if ( $( el ).find( 'option' ).length > 0 ) {
			args = {
				minimumResultsForSearch: 10,
				formatResult: formatResult,
				allowClear: true,
				width: '165px'
			};
		} else {
			args = {
				minimumInputLength: 3,
				allowClear: true,
				width: '165px',
				ajax: {
					url: ajaxurl,
					datatype: 'json',
					data: function( term ) {
						return {
							action: 'mainwp_wp_stream_filters', 
                                                        nonce: $( '#mainwp_creport_filters_user_search_nonce' ).val(),
							filter: $( el ).attr( 'name' ),
							q: term
						};
					},
					results: function( data ) {
						return { results: data };
					}
				},
				formatResult: formatResult,
				initSelection: function( element, callback ) {
					var id = $( element ).val();
					if ( '' !== id ) {
						$.post(
							ajaxurl,
							{
								action: 'mainwp_wp_stream_get_filter_value_by_id',
								filter: $(element).attr('name'),
								id: id
							},
							function( response ) {
								callback({
									id: id,
									text: response
								});
							},
							'json'
						);
					}
				}
			};
		}
		$( el ).select2( args );
	});

	var stream_select2_change_handler = function( e, input ) {
		var $placeholder_class = input.data( 'select-placeholder' );
		var $placeholder_child_class = $placeholder_class + '-child';
		var $placeholder = input.siblings( '.' + $placeholder_class );
		jQuery( '.' + $placeholder_child_class ).off().remove();
		if ( 'undefined' === typeof e.val ) {
			e.val = input.val().split( ',' );
		}
		$.each( e.val.reverse(), function( value, key ) {
			if ( null === key || '__placeholder__' === key || '' === key ) {
				return true;
			}
			$placeholder.after( $placeholder.clone( true ).attr( 'class', $placeholder_child_class ).val( key ) );
		});
	};
	$( '#tab-content-settings input[type=hidden].select2-select.with-source' ).each(function( k, el ) {
		var $input = $( el );
		$input.select2({
			multiple: true,
			width: 350,
			data: $input.data( 'values' ),
			query: function( query ) {
				var data = { results: [] };
				if ( 'undefined' !== typeof query.term ) {
					$.each( $input.data( 'values' ), function() {
						if ( query.term.length === 0 || this.text.toUpperCase().indexOf( query.term.toUpperCase() ) >= 0 ) {
							data.results.push( { id: this.id, text: this.text } );
						}
					});
				}
				query.callback( data );
			},
			initSelection: function( item, callback ) {
				callback( item.data( 'selected' ) );
			}
		}).on( 'change', function( e ) {
			stream_select2_change_handler( e , $input );
		}).trigger( 'change' );
	});
	$( '#tab-content-settings input[type=hidden].select2-select.ip-addresses' ).each(function( k, el ) {
		var $input = $( el );

		$input.select2({
			tags: $input.data( 'selected' ),
			width: 350,
			ajax: {
				type: 'POST',
				url: ajaxurl,
				dataType: 'json',
				quietMillis: 500,
				data: function( term ) {
					return {
						find: term,
						limit: 10,
						action: 'mainwp_stream_get_ips',
						nonce: $input.data( 'nonce' )
					};
				},
				results: function( response ) {
					var answer = { results: [] };

					if ( true !== response.success || undefined === response.data ) {
						return answer;
					}

					$.each( response.data, function( key, ip ) {
						answer.results.push({
							id: ip,
							text: ip
						});
					});

					return answer;
				}
			},
			initSelection: function( item, callback ) {
				callback( item.data( 'selected' ) );
			},
			formatNoMatches: function(){
				return '';
			},
			createSearchChoice: function( term ) {
				var ip_chunks = [];

				ip_chunks = term.match( /^(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})$/ );

				if ( null === ip_chunks ) {
					return;
				}

				// remove whole match
				ip_chunks.shift();

				ip_chunks = $.grep(
					ip_chunks,
					function( chunk ) {
						var numeric = parseInt(chunk, 10);
						return numeric <= 255 && numeric.toString() === chunk;
					}
				);

				if ( ip_chunks.length < 4 ) {
					return;
				}

				return {
					id: term,
					text: term
				};
			}
		}).on( 'change', function( e ) {
			stream_select2_change_handler( e , $input );
		}).trigger( 'change' );
	});
	var $input_user;
	$( '#tab-content-settings input[type=hidden].select2-select.authors_and_roles' ).each(function( k, el ) {
		$input_user = $( el );

		$input_user.select2({
			multiple: true,
			width: 350,
			ajax: {
				type: 'POST',
				url: ajaxurl,
				dataType: 'json',
				quietMillis: 500,
				data: function( term, page ) {
					return {
						find: term,
						limit: 10,
						pager: page,
						action: 'mainwp_stream_get_users',
						nonce: $input_user.data('nonce')
					};
				},
				results: function( response ) {
					var roles  = [],
						answer = [];

					roles = $.grep(
						$input_user.data( 'values' ),
						function( role ) {
							var roleVal = $input_user.data( 'select2' )
								.search
								.val()
								.toLowerCase();
							var rolePos = role
								.text
								.toLowerCase()
								.indexOf( roleVal );
							return rolePos >= 0;
						}
					);

					answer = {
						results: [
							{
								text: 'Roles',
								children: roles
							},
							{
								text: 'Users',
								children: []
							}
						]
					};

					if ( true !== response.success || undefined === response.data || true !== response.data.status ) {
						return answer;
					}
					$.each( response.data.users, function( k, user ) {
						if ( $.contains( roles, user.id ) ) {
							user.disabled = true;
						}
					});
					answer.results[ 1 ].children = response.data.users;
					// notice we return the value of more so Select2 knows if more results can be loaded
					return answer;
				}
			},
			formatResult: function( object, container ) {
				var result = object.text;

				if ( 'undefined' !== typeof object.icon && object.icon ) {
					result = '<img src="' + object.icon + '" class="wp-stream-select2-icon">' + result;
					// Add more info to the container
					container.attr( 'title', object.tooltip );
				}
				// Add more info to the container
				if ( 'undefined' !== typeof object.tooltip ) {
					container.attr( 'title', object.tooltip );
				} else if ( 'undefined' !== typeof object.user_count ) {
					container.attr( 'title', object.user_count );
				}
				return result;
			},
			formatSelection: function( object ){
				if ( $.isNumeric( object.id ) && object.text.indexOf( 'icon-users' ) < 0 ) {
					object.text += '<i class="icon16 icon-users"></i>';
				}

				return object.text;
			},
			initSelection: function( item, callback ) {
				callback( item.data( 'selected' ) );
			}
		});
	}).on( 'change', function( e ) {
		stream_select2_change_handler( e, $input_user );
	}).trigger( 'change' );

	$( window ).load(function() {
		$( '.mainwp_wp_stream_screen [type=search]' ).off( 'mousedown' );
	});

	// Confirmation on some important actions
	$( '#mainwp_wp_stream_general_delete_all_records, #mainwp_wp_stream_network_general_delete_all_records' ).click(function( e ) {
		if ( ! confirm( mainwp_wp_stream.i18n.confirm_purge ) ) {
			e.preventDefault();
		}
	});

	$( '#mainwp_wp_stream_general_reset_site_settings, #mainwp_wp_stream_network_general_reset_site_settings' ).click(function( e ) {
		if ( ! confirm( mainwp_wp_stream.i18n.confirm_defaults ) ) {
			e.preventDefault();
		}
	});


	// Heartbeat for Live Updates
	// runs only on stream page (not settings)
	$( document ).ready(function() {
		// Only run on page 1 when the order is desc and on page mainwp_wp_stream
		if (
			mainwp_wp_stream.current_screen.indexOf('_mainwp-reports-page') == -1 ||
			'1' !== mainwp_wp_stream.current_page ||
			'asc' === mainwp_wp_stream.current_order
		) {
			return;
		}

		var list_sel = '.mainwp_child_reports_wrap #the-list';

		// Set initial beat to fast. WP is designed to slow this to 15 seconds after 2.5 minutes.
		wp.heartbeat.interval( 'fast' );

		$( document ).on( 'heartbeat-send.child_reports', function( e, data ) {                                
                        if ($(list_sel).length == 0)
                            return;
                        
			data['wp-mainwp-stream-heartbeat'] = 'live-update';
			var last_item = $( list_sel + ' tr:first .column-id' );
			var last_id = 1;
			if ( last_item.length !== 0 ) {
				last_id = ( '' === last_item.text() ) ? 1 : last_item.text();                                
			}
                        var last_created_item = $( list_sel + ' tr:first .column-date span.timestamp' );
                        var last_created = 0;                        
                        if ( last_created_item.length !== 0 ) {				
                                last_created = last_created_item.attr('timestamp');                                
			}                      
			data['wp-mainwp-stream-heartbeat-last-id'] = last_id;
                        data['wp-mainwp-stream-heartbeat-last-created'] = last_created;
			data['wp-mainwp-stream-heartbeat-query']   = mainwp_wp_stream.current_query;
		});

		// Listen for "heartbeat-tick" on $(document).
		$( document ).on( 'heartbeat-tick.child_reports', function( e, data ) {

			// If this no rows return then we kill the script
			if ( ! data['wp-mainwp-stream-heartbeat'] || 0 === data['wp-mainwp-stream-heartbeat'].length ) {
				return;
			}

			// Get show on screen
			var show_on_screen = $( '#mainwp_child_reports_per_page' ).val();

			// Get all current rows
			var $current_items = $( list_sel + ' tr' );

			// Get all new rows
			var $new_items = $( data['wp-mainwp-stream-heartbeat'] );

			// Remove all class to tr added by WP and add new row class
			$new_items.removeClass().addClass( 'new-row' );

			//Check if first tr has the alternate class
			var has_class = ( $current_items.first().hasClass( 'alternate' ) );

			// Apply the good class to the list
			if ( $new_items.length === 1 && ! has_class ) {
				$new_items.addClass( 'alternate' );
			} else {
				var even_or_odd = ( 0 === $new_items.length % 2 && ! has_class ) ? 'even' : 'odd';
				// Add class to nth child because there is more than one element
				$new_items.filter( ':nth-child(' + even_or_odd + ')' ).addClass( 'alternate' );
			}

			// Add element to the dom
			$( list_sel ).prepend( $new_items );

			$( '.metabox-prefs input' ).each(function() {
				if ( true !== $( this ).prop( 'checked' ) ) {
					var label = $( this ).val();
					$( 'td.column-' + label ).hide();
				}
			});

			// Remove the number of element added to the end of the list table
			var slice_rows = show_on_screen - ( $new_items.length + $current_items.length );
			if ( slice_rows < 0 ) {
				$( list_sel + ' tr' ).slice( slice_rows ).remove();
			}

			// Remove the no items row
			$( list_sel + ' tr.no-items' ).remove();

			// Update pagination
			var total_items_i18n = data.total_items_i18n || '';
			if ( total_items_i18n ) {
				$( '.displaying-num' ).text( total_items_i18n );
				$( '.total-pages' ).text( data.total_pages_i18n );
				$( '.tablenav-pages' ).find( '.next-page, .last-page' ).toggleClass( 'disabled', data.total_pages === $( '.current-page' ).val() );
				$( '.tablenav-pages .last-page' ).attr( 'href', data.last_page_link );
			}

			// Allow others to hook in, ie: timeago
			$( list_sel ).parent().trigger( 'updated' );

			// Remove background after a certain amount of time
			setTimeout(function() {
				$('.new-row').addClass( 'fadeout' );
				setTimeout(function() {
					$( list_sel + ' tr' ).removeClass( 'new-row fadeout' );
				}, 500 );
			}, 3000 );

		});

		//Enable Live Update Checkbox Ajax
		$( '#enable_live_update' ).click(function() {
			var nonce   = $( '#mainwp_creport_live_update_nonce' ).val();
			var user    = $( '#enable_live_update_user' ).val();
			var checked = 'unchecked';
			if ( $( '#enable_live_update' ).is( ':checked' ) ) {
				checked = 'checked';
			}

			$.ajax({
				type: 'POST',
				url: ajaxurl,
				data: {
					action: 'mainwp_stream_enable_live_update',
					nonce: nonce,
					user: user,
					checked: checked
				},
				dataType: 'json',
				beforeSend: function() {
					$( '.stream-live-update-checkbox .spinner' ).show().css( { 'display': 'inline-block' } );
				},
				success: function() {
					$( '.stream-live-update-checkbox .spinner' ).hide();
				}
			});
		});

		function toggle_filter_submit() {
			var all_hidden = true;
			// If all filters are hidden, hide the button
			if ( $( 'div.metabox-prefs [id="date-hide"]' ).is( ':checked' ) ) {
				all_hidden = false;
			}
			var divs = $( 'div.alignleft.actions div.select2-container' );
			divs.each(function() {
				if ( ! $( this ).is( ':hidden' ) ) {
					all_hidden = false;
					return false;
				}
			});
			if ( all_hidden ) {
				$( 'input#record-query-submit' ).hide();
				$( 'span.filter_info' ).show();
			} else {
				$( 'input#record-query-submit' ).show();
				$( 'span.filter_info' ).hide();
			}
		}

		if ( $( 'div.metabox-prefs [id="date-hide"]' ).is( ':checked' ) ) {
			$( 'div.date-interval' ).show();
		} else {
			$( 'div.date-interval' ).hide();
		}

		$( 'div.actions select.chosen-select' ).each(function() {
			var name = $( this ).prop( 'name' );

			if ( $( 'div.metabox-prefs [id="' + name + '-hide"]' ).is( ':checked' ) ) {
				$( this ).prev( '.select2-container' ).show();
			} else {
				$( this ).prev( '.select2-container' ).hide();
			}
		});

		toggle_filter_submit();

		$( 'div.metabox-prefs [type="checkbox"]' ).click(function() {
			var id = $( this ).prop( 'id' );

			if ( 'date-hide' === id ) {
				if ( $( this ).is( ':checked' ) ) {
					$( 'div.date-interval' ).show();
				} else {
					$( 'div.date-interval' ).hide();
				}
			} else {
				id = id.replace( '-hide', '' );

				if ( $( this ).is( ':checked' ) ) {
					$( '[name="' + id + '"]' ).prev( '.select2-container' ).show();
				} else {
					$( '[name="' + id + '"]' ).prev( '.select2-container' ).hide();
				}
			}

			toggle_filter_submit();
		});

		$( '#ui-datepicker-div' ).addClass( 'stream-datepicker' );
	});

	// Relative time
	$( 'table.wp-list-table' ).on( 'updated', function() {
		var timeObjects = $( this ).find( 'time.relative-time' );
		timeObjects.each( function( i, el ) {
			var timeEl = $( el );
			timeEl.removeClass( 'relative-time' );
			$( '<strong><time datetime="' + timeEl.attr( 'datetime' ) + '" class="timeago"/></time></strong><br/>' )
				.prependTo( timeEl.parent().parent() )
				.find( 'time.timeago' )
				.timeago();
		});
	}).trigger( 'updated' );

	var intervals = {
		init: function( $wrapper ) {
			this.wrapper = $wrapper;
			this.save_interval( this.wrapper.find( '.button-primary' ), this.wrapper );

			this.$ = this.wrapper.each( function( i, val ) {
				var container   = $( val ),
					dateinputs  = container.find( '.date-inputs' ),
					from        = container.find( '.field-from' ),
					to          = container.find( '.field-to' ),
					to_remove   = to.prev( '.date-remove' ),
					from_remove = from.prev( '.date-remove' ),
					predefined  = container.children( '.field-predefined' ),
					datepickers = $( '' ).add( to ).add( from );

				if ( jQuery.datepicker ) {

					// Apply a GMT offset due to Date() using the visitor's local time
					var	siteGMTOffsetHours  = parseFloat( mainwp_wp_stream.gmt_offset ),
						localGMTOffsetHours = new Date().getTimezoneOffset() / 60 * -1,
						totalGMTOffsetHours = siteGMTOffsetHours - localGMTOffsetHours,
						localTime           = new Date(),
						siteTime            = new Date( localTime.getTime() + ( totalGMTOffsetHours * 60 * 60 * 1000 ) ),
						dayOffset           = '0';

					// check if the site date is different from the local date, and set a day offset
					if ( localTime.getDate() !== siteTime.getDate() || localTime.getMonth() !== siteTime.getMonth() ) {
						if ( localTime.getTime() < siteTime.getTime() ) {
							dayOffset = '+1d';
						} else {
							dayOffset = '-1d';
						}
					}

					datepickers.datepicker({
						dateFormat: 'yy/mm/dd',
						maxDate: dayOffset,
						defaultDate: siteTime,
						beforeShow: function() {
							$( this ).prop( 'disabled', true );
						},
						onClose: function() {
							$( this ).prop( 'disabled', false );
						}
					});

					datepickers.datepicker( 'widget' ).addClass( 'stream-datepicker' );
				}

				predefined.select2({
					'allowClear': true
				});

				if ( '' !== from.val() ) {
					from_remove.show();
				}

				if ( '' !== to.val() ) {
					to_remove.show();
				}

				predefined.on({
					'change': function () {
						var value    = $( this ).val(),
							option   = predefined.find( '[value="' + value + '"]' ),
							to_val   = option.data( 'to' ),
							from_val = option.data( 'from' );

						if ( 'custom' === value ) {
							dateinputs.show();
							return false;
						} else {
							dateinputs.hide();
							datepickers.datepicker( 'hide' );
						}

						from.val( from_val ).trigger( 'change', [ true ] );
						to.val( to_val ).trigger( 'change', [ true ] );

						if ( jQuery.datepicker && datepickers.datepicker( 'widget' ).is( ':visible' ) ) {
							datepickers.datepicker( 'refresh' ).datepicker( 'hide' );
						}
					},
					'select2-removed': function() {
						predefined.val( '' ).trigger( 'change' );
					},
					'check_options': function () {
						if ( '' !== to.val() && '' !== from.val() ) {
							var	option = predefined
								.find( 'option' )
								.filter( '[data-to="' + to.val() + '"]' )
								.filter( '[data-from="' + from.val() + '"]' );
							if ( 0 !== option.length ) {
								predefined.val( option.attr( 'value' ) ).trigger( 'change', [ true ] );
							} else {
								predefined.val( 'custom' ).trigger( 'change', [ true ] );
							}
						} else if ( '' === to.val() && '' === from.val() ) {
							predefined.val( '' ).trigger( 'change', [ true ] );
						} else {
							predefined.val( 'custom' ).trigger( 'change', [ true ] );
						}
					}
				});

				from.on( 'change', function() {
					if ( '' !== from.val() ) {
						from_remove.show();
						to.datepicker( 'option', 'minDate', from.val() );
					} else {
						from_remove.hide();
					}

					if ( true === arguments[ arguments.length - 1 ] ) {
						return false;
					}

					predefined.trigger( 'check_options' );
				});

				to.on( 'change', function() {
					if ( '' !== to.val() ) {
						to_remove.show();
						from.datepicker( 'option', 'maxDate', to.val() );
					} else {
						to_remove.hide();
					}

					if ( true === arguments[ arguments.length - 1 ] ) {
						return false;
					}

					predefined.trigger( 'check_options' );
				});

				// Trigger change on load
				predefined.trigger( 'change' );

				$( '' ).add( from_remove ).add( to_remove ).on( 'click', function() {
					$( this ).next( 'input' ).val( '' ).trigger( 'change' );
				});
			});
		},

		save_interval: function( $btn ) {
			var $wrapper = this.wrapper;
			$btn.click( function() {
				var data = {
					key:   $wrapper.find( 'select.field-predefined' ).find( ':selected' ).val(),
					start: $wrapper.find( '.date-inputs .field-from' ).val(),
					end:   $wrapper.find( '.date-inputs .field-to' ).val()
				};

				// Add params to URL
				$( this ).attr( 'href', $( this ).attr( 'href' ) + '&' + $.param( data ) );
			});
		}
	};

	$( document ).ready( function() {
		intervals.init( $( '.date-interval' ) );
	});
});
