import {
    FETCH_PROJECTS_STATS
} from 'config/actions';

const initialState = {
    byMonth: null,
    wasRejected: null,
    error: ''
};

export default function projectsByMonthReducer(state = initialState, action) {

    switch (action.type) {
        case FETCH_PROJECTS_STATS + '_PENDING':
        {
            return {
                ...state
            };
        }
        case FETCH_PROJECTS_STATS + '_FULFILLED':
        {
            const stats = action.payload.data.data;
            const projectsByMonth = stats.projects.by_month;

            return {
                ...state,
                wasRejected: false,
                byMonth: projectsByMonth
            };
        }
        case FETCH_PROJECTS_STATS + '_REJECTED':
        {
            return {
                ...state,
                wasRejected: true,
                error: action.payload.message
            };
        }
        default:
            return state;
    }
}
