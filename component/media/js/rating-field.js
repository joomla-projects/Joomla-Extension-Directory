/**
 * Rating star field
 */
(function($) {
	$(document).ready(function(){
		let ratingSelectors = [];
		$('.rating-selector').each(function(index) {
			const widget = $(this);
			let el = widget.find('.rating-stars');
			let input = widget.find('input');
			let liveRating = widget.find('.live-rating');
			let emptyRating = widget.find('.rating-stars-empty');
			let noScore = widget.find('.no-score');
			noScore.toggle(input[0].value < 0.5);
			emptyRating[0].value = input[0].value < 0.5 ? 1 : 0;

			ratingSelectors[index] = raterJs( {
				// starSize:screen.width > 435 ? 32 : 23, 
				starSize: 32, 
				rating: input[0].value > 0 ? input[0].value/20 : 0,
				max: 5,
				step: 0.5,
				showToolTip: false,
				element: el[0], 
				rateCallback:function rateCallback(rating, done) {
					rating = rating == 0 ? -1 : rating;
					this.setRating(rating);
					noScore.hide();
					const rated = rating > 0; 
					input[0].value = rated ? rating*20 : -1;
					emptyRating[0].value = rated ? 0 : 1;
					liveRating.toggleClass('muted',false);
					widget.toggleClass('rated',rated); 
					el.closest('.control-group').removeClass('error');
					done(); 
				},
				onHover:function(currentIndex, currentRating) {
					noScore.hide();
					widget.toggleClass('rated',true);
					liveRating[0].textContent = ratingText(currentIndex);
					liveRating.toggleClass('muted',true);
				}, 
				onLeave:function(currentIndex, currentRating) {
					liveRating.toggleClass('muted',false);
					if ( emptyRating[0].value == 1 ) {
						noScore.show();
						liveRating[0].textContent = '';
						widget.toggleClass('rated',false); 
					} else {
						liveRating[0].textContent = ratingText(currentRating); 
						widget.toggleClass('rated',true);
						noScore.hide();
					}
				}
			});		
	
			widget.find('.clear-rating').on('click',function(e) {
				e.preventDefault();
				ratingSelectors[index].clear();
				emptyRating[0].value = 1;
				liveRating[0].textContent = '';
				widget.toggleClass('rated',false);
				noScore.show();
			});
		});

		function ratingText(rating) {
			switch(true) {
				case (rating > 0 && rating <= 1):
					return 'Very poor';
				case (rating > 1 && rating <= 2):
					return 'Poor';
				case (rating > 2 && rating <= 3):
					return 'Ok';
				case (rating > 3 && rating <= 4):
					return 'Good';
				case (rating > 4 && rating <= 5):
					return 'Excellent';
				default:
					return '';
			}
		}

	});
})(jQuery);
