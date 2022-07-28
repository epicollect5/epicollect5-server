import {
    FETCH_PROJECTS_STATS
} from 'config/actions';

const initialState = {
    overall: null,
    private: {
        listed: null,
        hidden: null
    },
    public: {
        listed: null,
        hidden: null
    },
    wasRejected: null,
    error: ''
};

export default function projectsTotalReducer(state = initialState, action) {

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
            const projectsTotal = stats.projects.total;
            const overall = projectsTotal.public.hidden + projectsTotal.private.hidden + projectsTotal.public.listed + projectsTotal.private.listed;

            return {
                ...state,
                wasRejected: false,
                overall,
                private: projectsTotal.private,
                public: projectsTotal.public
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
