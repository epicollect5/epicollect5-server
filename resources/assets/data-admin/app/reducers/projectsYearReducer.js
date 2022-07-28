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

export default function projectsYearReducer(state = initialState, action) {

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
            const projectsYear = stats.projects.year;

            const overall =
                projectsYear.public.hidden +
                projectsYear.private.hidden +
                projectsYear.public.listed +
                projectsYear.private.listed;

            return {
                ...state,
                wasRejected: false,
                overall,
                private: projectsYear.private,
                public: projectsYear.public
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
