<div class="container mt-5">

    <div class="row justify-content-center">

        <div class="col-md-5">

            <div class="card shadow">

                <div class="card-header text-center">

                    <h3>Flood Detection System</h3>

                </div>

                <div class="card-body">

                    <?php if(isset($error)): ?>

                        <div class="alert alert-danger">

                            <?= $error; ?>

                        </div>

                    <?php endif; ?>

                    <form
                        method="POST"
                        action="/login"
                    >

                        <div class="mb-3">

                            <label class="form-label">

                                Username

                            </label>

                            <input
                                type="text"
                                name="username"
                                class="form-control"
                                required
                            >

                        </div>

                        <div class="mb-3">

                            <label class="form-label">

                                Password

                            </label>

                            <input
                                type="password"
                                name="password"
                                class="form-control"
                                required
                            >

                        </div>

                        <button
                            class="btn btn-primary w-100"
                            type="submit"
                        >

                            Login JWT

                        </button>

                    </form>

                    <hr>

                    <a
                        href="http://localhost:3000/auth/google"
                        class="btn btn-danger w-100"
                    >

                        Login dengan Google

                    </a>

                </div>

            </div>

        </div>

    </div>

</div>