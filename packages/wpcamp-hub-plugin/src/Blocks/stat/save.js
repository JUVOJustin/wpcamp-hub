/**
 * Stat — saved markup.
 *
 * The final formatted number is rendered as the text content (works without
 * JS). The view script reads data-target / data-separator to animate it up
 * from zero when scrolled into view.
 */
import { useBlockProps, RichText } from '@wordpress/block-editor';

import { formatNumber } from './accents';

export default function save( { attributes } ) {
	const { number, prefix, suffix, thousandsSeparator, label, accent } =
		attributes;

	const blockProps = useBlockProps.save( {
		className: `wpch-stat wpch-stat--${ accent }`,
	} );

	return (
		<div { ...blockProps }>
			<div className="wpch-stat__num">
				<RichText.Content
					tagName="span"
					className="wpch-stat__prefix"
					value={ prefix }
				/>
				<span
					className="wpch-stat__value"
					data-target={ number }
					data-separator={ thousandsSeparator ? '1' : '0' }
				>
					{ formatNumber( number, thousandsSeparator ) }
				</span>
				<RichText.Content
					tagName="span"
					className="wpch-stat__suffix"
					value={ suffix }
				/>
			</div>
			<RichText.Content
				tagName="div"
				className="wpch-stat__label"
				value={ label }
			/>
		</div>
	);
}
