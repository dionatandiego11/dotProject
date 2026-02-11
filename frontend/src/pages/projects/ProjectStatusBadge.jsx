import Badge from '../../components/ui/Badge'
import { getStatusBadgeById, getStatusLabelById } from './projectStatusUtils'

export default function ProjectStatusBadge({ status }) {
    const variant = getStatusBadgeById(status)
    return (
        <Badge variant={variant} withDot>
            {getStatusLabelById(status)}
        </Badge>
    )
}
