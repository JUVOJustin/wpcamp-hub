/**
 * Stats Band — saved markup.
 */
import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';

export default function save( { attributes } ) {
	const { columns } = attributes;

	const blockProps = useBlockProps.save( {
		className: 'wpch-stats',
		style: { '--wpch-stats-cols': columns },
	} );

	const innerBlocksProps = useInnerBlocksProps.save( {
		className: 'wpch-stats__grid',
	} );

	return (
		<section { ...blockProps }>
			<div className="wpch-stats__inner">
				<div { ...innerBlocksProps } />
			</div>
		</section>
	);
}
