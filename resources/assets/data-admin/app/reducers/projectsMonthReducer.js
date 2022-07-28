import {
    FETCH_PROJECTS_STATS
} from 'config/actions';

const initialState = {
    overall: null,
    private: {
        private: null,
        listed: null
    },
    public: {
        private: null,
        listed: null
    },
    wasRejected: null,
    error: ''
};

export default function projectsMonthReducer(state = initialState, action) {

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
            const projectsMonth = stats.projects.month;

            const overall =
                projectsMonth.public.hidden +
                projectsMonth.private.hidden +
                projectsMonth.public.listed +
                projectsMonth.private.listed;

            return {
                ...state,
                wasRejected: false,
                overall,
                private: projectsMonth.private,
                public: projectsMonth.public
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
